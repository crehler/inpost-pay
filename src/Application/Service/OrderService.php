<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Application\Dto\{CreateOrderDto, OrderEventDto};
use Crehler\InpostPay\Application\Dto\EventData\PaymentEventData;
use Crehler\InpostPay\Application\Event\OrderCartPreparedEvent;
use Crehler\InpostPay\Domain\Aggregate\Order;
use Crehler\InpostPay\Domain\Exception\{InvalidBasketException, InvalidOrderEventException, InvalidOrderException, OrderNotFoundException};
use Crehler\InpostPay\Domain\ValueObject\{DeliveryType, KeyValue, PaymentStatus, PaymentType, ServiceCode};
use Crehler\InpostPay\Domain\ValueObject\Order\{CustomerInfo, LegalForm};
use Crehler\InpostPay\Infrastructure\Logger\ExtendedLogger;
use Crehler\InpostPay\Infrastructure\Persistence\Repository\{InpostPayPaymentMethodResolver, ShopwareOrderRepository};
use Crehler\InpostPay\Infrastructure\Provider\{DeliveryMappingProvider, InpostPayConfigProvider, ParcelLockerAddressProvider, ServiceOptionsProvider};
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\{OrderConversionContext, OrderConverter};
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\{CalculatedTaxCollection, TaxRuleCollection};
use Shopware\Core\Checkout\Cart\Transaction\Struct\{Transaction, TransactionCollection};
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionStateHandler, OrderTransactionStates};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use ValueError;

use function array_map;
use function array_merge;
use function explode;
use function is_array;
use function sprintf;
use function trim;

readonly class OrderService
{
    public function __construct(
        private CartOperationService $cartOperationService,
        private CartDataExtractor $cartDataExtractor,
        private OrderConverter $orderConverter,
        #[Target('order.repository')]
        private EntityRepository $orderRepository,
        private InpostBasketSessionService $basketSessionService,
        private CustomerMatchingService $customerMatchingService,
        private InpostPayPaymentMethodResolver $paymentMethodResolver,
        private InpostPayConfigProvider $configProvider,
        private ShopwareOrderRepository $shopwareOrderRepository,
        private OrderDataExtractor $orderDataExtractor,
        private CartService $cartService,
        private BasePricingContextFactory $basePricingContextFactory,
        private OrderTransactionStateHandler $transactionStateHandler,
        private ServiceOptionsProvider $serviceOptionsProvider,
        private DeliveryMappingProvider $deliveryMappingProvider,
        private ParcelLockerAddressProvider $parcelLockerAddressProvider,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private StatusLabelResolver $statusLabelResolver,
        private ExtendedLogger $extendedLogger,
    ) {
    }

    public function createOrder(CreateOrderDto $orderDto): Order
    {
        try {
            $basketId = $orderDto->orderDetails->basketId;

            $session = $this->basketSessionService->getSessionByBasketId($basketId);
            if (!$session) {
                throw new InvalidBasketException(sprintf('Basket session not found for basketId: %s', $basketId));
            }

            $salesChannelId = $session->getSalesChannelId();
            // The cart lives under the live Shopware token, which can differ from the
            // immutable InPost basketId after a context-token change (e.g. guest login).
            $cartToken = $session->getCartToken() ?? $basketId;
            $customerInfo = $orderDto->accountInfo->toDomain();
            $deliveryDetails = $orderDto->delivery->toDomain();

            $billingAddress = $orderDto->accountInfo->clientAddress;
            $tempContext = $this->cartOperationService->createSalesChannelContext($cartToken, $salesChannelId);

            $customerWithAddress = $this->customerMatchingService->findOrCreateCustomerWithAddress(
                $customerInfo,
                $billingAddress,
                $salesChannelId,
                $tempContext->getContext()
            );

            $customer = $customerWithAddress['customer'];
            $billingAddressId = $customerWithAddress['billingAddressId'];

            $shippingAddressId = $billingAddressId;

            // InPost Pay omits delivery_address for parcel-locker (APM) orders and
            // sends only the locker code in delivery_point. Resolve the locker's
            // physical address so it is saved as the shipping address instead of
            // falling back to the customer's billing address.
            $deliveryAddress = $orderDto->delivery->deliveryAddress;
            if (
                $deliveryAddress === null
                && $orderDto->delivery->deliveryType === DeliveryType::APM
                && $orderDto->delivery->deliveryPoint !== null
            ) {
                $deliveryAddress = $this->parcelLockerAddressProvider->resolveAddress(
                    $orderDto->delivery->deliveryPoint
                );
            }

            if ($deliveryAddress !== null) {
                [$firstName, $lastName] = $this->parseDeliveryName(
                    $deliveryAddress->name,
                    $customerInfo->firstName,
                    $customerInfo->lastName
                );

                $shippingCustomerInfo = new CustomerInfo(
                    firstName: $firstName,
                    lastName: $lastName,
                    email: $customerInfo->email,
                    phone: $orderDto->delivery->phoneNumber,
                    address: $deliveryAddress,
                );

                $shippingAddressId = $this->customerMatchingService->findOrCreateAddressForCustomer(
                    $customer,
                    $shippingCustomerInfo,
                    $deliveryAddress,
                    $tempContext->getContext()
                );
            }

            $inpostPayMethodId = $this->paymentMethodResolver->getPaymentMethodId(
                $tempContext->getContext(),
                $orderDto->orderDetails->paymentType,
            );

            $this->extendedLogger->info('[order] Resolved payment method for payment type', [
                'basket_id' => $basketId,
                'payment_type' => $orderDto->orderDetails->paymentType->value,
                'resolved_payment_method_id' => $inpostPayMethodId,
            ], $salesChannelId);

            $shippingMethodId = $this->deliveryMappingProvider->getShippingMethodIdForDeliveryType(
                $orderDto->delivery->deliveryType,
                $salesChannelId
            );

            if ($shippingMethodId === null) {
                $this->logger->error('No shipping method mapping configured for InPost delivery type', [
                    'basket_id' => $basketId,
                    'delivery_type' => $orderDto->delivery->deliveryType->value,
                    'sales_channel_id' => $salesChannelId,
                ]);

                throw InvalidOrderException::missingShippingMethodMapping($orderDto->delivery->deliveryType->value);
            }

            $this->extendedLogger->info('[order] Resolved shipping method for delivery type', [
                'basket_id' => $basketId,
                'sales_channel_id' => $salesChannelId,
                'delivery_type' => $orderDto->delivery->deliveryType->value,
                'resolved_shipping_method_id' => $shippingMethodId,
                'apm_method_ids' => $this->deliveryMappingProvider->getMethodIdsByType(DeliveryType::APM, $salesChannelId),
                'courier_method_ids' => $this->deliveryMappingProvider->getMethodIdsByType(DeliveryType::COURIER, $salesChannelId),
                'digital_method_ids' => $this->deliveryMappingProvider->getMethodIdsByType(DeliveryType::DIGITAL, $salesChannelId),
            ], $salesChannelId);

            $context = $this->cartOperationService->createSalesChannelContextWithCustomer(
                $cartToken,
                $salesChannelId,
                $customer->getId(),
                $inpostPayMethodId,
                $shippingMethodId,
            );

            $this->extendedLogger->info('[order] Shipping and payment method on built context', [
                'basket_id' => $basketId,
                'requested_shipping_method_id' => $shippingMethodId,
                'context_shipping_method_id' => $context->getShippingMethod()->getId(),
                'context_shipping_method_name' => $context->getShippingMethod()->getName(),
                'requested_payment_method_id' => $inpostPayMethodId,
                'context_payment_method_id' => $context->getPaymentMethod()->getId(),
                'context_payment_method_name' => $context->getPaymentMethod()->getName(),
                'context_payment_method_handler' => $context->getPaymentMethod()->getHandlerIdentifier(),
            ], $salesChannelId);

            $cart = $this->cartOperationService->loadCartByBasketId($basketId);

            // Let listeners adjust the cart to the chosen InPost delivery before recalculation.
            $this->eventDispatcher->dispatch(
                new OrderCartPreparedEvent($cart, $context, $orderDto->delivery->deliveryType, $basketId)
            );

            // Add COD/PWW service cost to the delivery price.
            $deliveryCodes = $orderDto->delivery->deliveryCodes ?? [];
            $servicesCostGross = $this->getOptionalServicesCostGross($deliveryCodes, $salesChannelId);
            $cart = $this->applyServicesCostToCart($cart, $servicesCostGross, $context);

            // Force the real payment method: the base (online) pricing recalculate overrode it.
            $existingTransaction = $cart->getTransactions()->first();
            if ($existingTransaction !== null) {
                $cart->setTransactions(new TransactionCollection([
                    new Transaction($existingTransaction->getAmount(), $inpostPayMethodId),
                ]));
            }

            $conversionContext = (new OrderConversionContext())
                ->setIncludeCustomer(true)
                ->setIncludeBillingAddress(true)
                ->setIncludeDeliveries(true)
                ->setIncludeTransactions(true)
                ->setIncludeOrderDate(true);

            $cartDeliveryMethods = [];
            foreach ($cart->getDeliveries() as $cartDelivery) {
                $cartDeliveryMethods[] = [
                    'id' => $cartDelivery->getShippingMethod()->getId(),
                    'name' => $cartDelivery->getShippingMethod()->getName(),
                ];
            }
            $this->extendedLogger->info('[order] Cart deliveries after recalculate', [
                'basket_id' => $basketId,
                'context_shipping_method_id' => $context->getShippingMethod()->getId(),
                'cart_delivery_methods' => $cartDeliveryMethods,
                'cart_transaction_payment_method_id' => $cart->getTransactions()->first()?->getPaymentMethodId(),
            ], $salesChannelId);

            $orderData = $this->orderConverter->convertToOrder($cart, $context, $conversionContext);
            $shopwareOrderId = $orderData['id'];

            $orderDataDeliveryMethods = [];
            foreach ($orderData['deliveries'] ?? [] as $od) {
                $orderDataDeliveryMethods[] = $od['shippingMethodId'] ?? null;
            }
            $this->extendedLogger->info('[order] orderData delivery shipping methods', [
                'basket_id' => $basketId,
                'order_data_shipping_method_ids' => $orderDataDeliveryMethods,
            ], $salesChannelId);

            $this->overrideOrderAddresses($orderData, $billingAddressId, $shippingAddressId, $tempContext->getContext());

            if ($orderDto->orderDetails->orderComments) {
                $orderData['customerComment'] = $orderDto->orderDetails->orderComments;
            }

            if ($orderDto->delivery->mail !== null && $orderDto->delivery->mail !== '') {
                $orderData['orderCustomer']['email'] = $orderDto->delivery->mail;
            }

            // A matched Shopware account may have an empty name; fall back to the buyer name from InPost account_info.
            if (trim((string) ($orderData['orderCustomer']['firstName'] ?? '')) === '') {
                $orderData['orderCustomer']['firstName'] = $customerInfo->firstName;
            }
            if (trim((string) ($orderData['orderCustomer']['lastName'] ?? '')) === '') {
                $orderData['orderCustomer']['lastName'] = $customerInfo->lastName;
            }

            if (
                trim((string) $orderData['orderCustomer']['firstName']) === ''
                || trim((string) $orderData['orderCustomer']['lastName']) === ''
            ) {
                throw new InvalidBasketException(sprintf('Missing customer name for InPost Pay order (basketId: %s)', $basketId));
            }

            if ($orderDto->invoiceDetails !== null && $orderDto->invoiceDetails->legalForm === LegalForm::COMPANY->value) {
                $invoice = $orderDto->invoiceDetails;
                $vatId = trim(($invoice->taxIdPrefix ?? '') . ($invoice->taxId ?? ''));

                $street = trim(($invoice->street ?? '') . ' ' . ($invoice->building ?? ''));
                if ($invoice->flat) {
                    $street .= '/' . $invoice->flat;
                }
                $street = trim($street);

                if (isset($orderData['addresses']) && is_array($orderData['addresses'])) {
                    foreach ($orderData['addresses'] as &$address) {
                        if (($address['id'] ?? null) === ($orderData['billingAddressId'] ?? null)) {
                            if ($invoice->companyName) {
                                $address['company'] = $invoice->companyName;
                            }
                            if ($vatId !== '') {
                                $address['vatId'] = $vatId;
                            }
                            if ($street !== '') {
                                $address['street'] = $street;
                            }
                            if ($invoice->city) {
                                $address['city'] = $invoice->city;
                            }
                            if ($invoice->postalCode) {
                                $address['zipcode'] = $invoice->postalCode;
                            }
                            if ($invoice->countryCode) {
                                $address['countryId'] = $this->customerMatchingService->resolveCountryId($invoice->countryCode, $context->getContext());
                            }
                            break;
                        }
                    }
                    unset($address);
                }

                if ($vatId !== '') {
                    $orderData['orderCustomer']['vatIds'] = [$vatId];
                }
            }

            $this->orderRepository->create([$orderData], $context->getContext());

            if ($orderDto->orderDetails->orderComments) {
                $partialOrderIds = $orderData['customFields']['partialOrdersIds'] ?? [];
                if (!empty($partialOrderIds)) {
                    $partialCommentUpdates = array_map(
                        static fn (string $partialOrderId): array => [
                            'id' => $partialOrderId,
                            'customerComment' => $orderDto->orderDetails->orderComments,
                        ],
                        $partialOrderIds
                    );
                    $this->orderRepository->update($partialCommentUpdates, $context->getContext());
                }
            }

            $this->orderRepository->update([
                [
                    'id' => $shopwareOrderId,
                    'customFields' => [
                        'inpost_pay_payment_type' => $orderDto->orderDetails->paymentType->value,
                        'inpost_pay_basket_id' => $basketId,
                        'inpost_pay_order_created_at' => (new DateTimeImmutable())->format('c'),
                        'inpost_pay_delivery_codes' => $deliveryCodes,
                        'inpost_pay_services_cost_gross' => $servicesCostGross,
                        'inpost_pay_account_mail' => $orderDto->accountInfo->mail,
                        'inpost_pay_delivery_mail' => $orderDto->delivery->mail,
                        'inpost_pay_delivery_type' => $orderDto->delivery->deliveryType->value,
                        'inpost_pay_delivery_point' => $orderDto->delivery->deliveryPoint,
                    ],
                ],
            ], $context->getContext());

            $status = OrderTransactionStates::STATE_OPEN;
            if ($orderDto->orderDetails->paymentType === PaymentType::CASH_ON_DELIVERY) {
                $createdOrder = $this->shopwareOrderRepository->findOrderById($shopwareOrderId, $context->getContext());
                $transaction = $createdOrder?->getTransactions()?->first();
                if ($transaction !== null) {
                    $this->transactionStateHandler->process($transaction->getId(), $context->getContext());
                    $status = OrderTransactionStates::STATE_IN_PROGRESS;
                }
            }

            $orderLines = $this->cartDataExtractor->extractProductsToOrderLines($cart, $context);

            $consents = array_map(
                fn ($consentDto) => $consentDto->toDomain(),
                $orderDto->consents
            );

            $pricing = $this->cartDataExtractor->calculateOrderPricing($cart, $context);

            $widgetConfig = $this->configProvider->getWidgetConfig($context);
            $posId = $widgetConfig->postId ?? '';

            $statusDescription = $this->statusLabelResolver->transactionLabel($status, $context->getSalesChannelId());

            $invoice = null;
            if ($orderDto->invoiceDetails !== null) {
                $invoice = $orderDto->invoiceDetails->toDomain();
            }

            $analytics = $session->getBasketAnalytics();
            $additionalParams = $orderDto->orderDetails->additionalParameters ?? [];

            if (!$analytics->isEmpty()) {
                $analyticsKeyValues = array_map(
                    fn (array $item) => new KeyValue($item['key'], $item['value']),
                    $analytics->toKeyValueArray()
                );
                $additionalParams = array_merge($additionalParams, $analyticsKeyValues);
            }

            $createdOrder = $this->shopwareOrderRepository->findOrderById($shopwareOrderId, $context->getContext());
            $customerOrderId = $createdOrder?->getOrderNumber();

            $order = new Order(
                id: $shopwareOrderId,
                merchantBasketId: $basketId,
                merchantPosId: $posId,
                status: $status,
                currency: $orderDto->orderDetails->currency,
                createdAt: new DateTimeImmutable(),
                customer: $customerInfo,
                delivery: $deliveryDetails,
                pricing: $pricing,
                paymentType: $orderDto->orderDetails->paymentType,
                items: $orderLines,
                consents: $consents,
                additionalParams: $additionalParams,
                invoice: $invoice,
                comments: $orderDto->orderDetails->orderComments,
                deliveryReferences: [],
                merchantStatusDesc: $statusDescription,
                customerOrderId: $customerOrderId,
            );

            $this->basketSessionService->linkSessionToOrder($basketId, $shopwareOrderId);

            $this->restoreBaseShippingOnCart($cart, $context);

            // The order is created directly via the order repository (not through
            // CartOrderRoute), so Shopware does not emit CheckoutOrderPlacedEvent
            // on its own. Without it, every Flow Builder flow triggered by
            // `checkout.order.placed` is skipped for InPost Pay orders - most
            // notably the order confirmation e-mail. Dispatch it explicitly with
            // the fully-loaded order so those flows run just like for a regular
            // checkout.
            if ($createdOrder !== null) {
                $this->eventDispatcher->dispatch(
                    new CheckoutOrderPlacedEvent(
                        $context->getContext(),
                        $createdOrder,
                        $salesChannelId,
                    ),
                );
            }

            return $order;
        } catch (InvalidBasketException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Failed to create order: %s', $e->getMessage()), previous: $e);
        }
    }

    public function getOrderById(string $orderId): Order
    {
        try {
            $context = Context::createDefaultContext();
            $shopwareOrder = $this->shopwareOrderRepository->findOrderById($orderId, $context);

            if (!$shopwareOrder) {
                throw new OrderNotFoundException(sprintf('Order not found: %s', $orderId));
            }

            return $this->orderDataExtractor->mapShopwareOrderToDomain($shopwareOrder);
        } catch (OrderNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new OrderNotFoundException(sprintf('Failed to retrieve order: %s', $e->getMessage()), previous: $e);
        }
    }

    public function handleOrderEvent(string $orderId, OrderEventDto $eventDto): array
    {
        try {
            $context = Context::createDefaultContext();

            $shopwareOrder = $this->shopwareOrderRepository->findOrderById($orderId, $context);

            if (!$shopwareOrder) {
                throw OrderNotFoundException::withId($orderId);
            }

            $transaction = $shopwareOrder->getTransactions()->first();
            if (!$transaction) {
                throw InvalidOrderEventException::noTransaction();
            }

            $currentState = $transaction->getStateMachineState()->getTechnicalName();
            $paymentStatus = $eventDto->eventData->paymentStatus;

            $this->transitionPaymentState(
                $transaction->getId(),
                $paymentStatus,
                $currentState,
                $context
            );

            $this->savePaymentEventData(
                $orderId,
                $eventDto->eventData,
                $context
            );

            $updatedOrder = $this->shopwareOrderRepository->findOrderById($orderId, $context);
            $updatedTransaction = $updatedOrder->getTransactions()->first();

            $statusDescription = $this->buildOrderStatusDescription($updatedOrder, $updatedTransaction);

            $trackingCodes = $this->orderDataExtractor->extractTrackingCodes($updatedOrder);

            return [
                'order_merchant_status_description' => $statusDescription,
                'delivery_references_list' => $trackingCodes,
            ];
        } catch (OrderNotFoundException|InvalidOrderEventException $e) {
            throw $e;
        } catch (IllegalTransitionException $e) {
            $this->logger->info('Ignoring illegal payment state transition (idempotent)', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return $this->getCurrentOrderStatus($orderId);
        } catch (Throwable $e) {
            throw new InvalidOrderEventException(sprintf('Failed to process order payment event: %s', $e->getMessage()), previous: $e);
        }
    }

    private function transitionPaymentState(
        string $transactionId,
        PaymentStatus $targetStatus,
        string $currentState,
        Context $context,
    ): void {
        if (!$targetStatus->requiresStateChange()) {
            return;
        }

        match ($targetStatus) {
            PaymentStatus::AUTHORIZED => $this->transactionStateHandler->paid($transactionId, $context),
            PaymentStatus::COMPLETED => $this->transactionStateHandler->paid($transactionId, $context),
            PaymentStatus::FAILED => $this->transactionStateHandler->fail($transactionId, $context),
            PaymentStatus::CANCELLED => $this->transactionStateHandler->cancel($transactionId, $context),
            PaymentStatus::PENDING => $this->transactionStateHandler->process($transactionId, $context),
            PaymentStatus::REFUNDED => $this->transactionStateHandler->refund($transactionId, $context),
            PaymentStatus::DECLINED => $this->transactionStateHandler->fail($transactionId, $context),
            PaymentStatus::ERROR => $this->transactionStateHandler->fail($transactionId, $context),
            PaymentStatus::COD => $this->handleCodPaymentState($transactionId, $currentState, $context),
        };
    }

    private function handleCodPaymentState(
        string $transactionId,
        string $currentState,
        Context $context,
    ): void {
        if ($currentState === OrderTransactionStates::STATE_OPEN) {
            $this->transactionStateHandler->process($transactionId, $context);
        }
    }

    private function savePaymentEventData(
        string $orderId,
        PaymentEventData $eventData,
        Context $context,
    ): void {
        $polishDescription = $this->statusLabelResolver->paymentLabel($eventData->paymentStatus);

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'customFields' => [
                    'inpost_pay_payment_status' => $eventData->paymentStatus->value,
                    'inpost_pay_payment_status_description' => $polishDescription,
                    'inpost_pay_payment_id' => $eventData->paymentId,
                    'inpost_pay_payment_reference' => $eventData->paymentReference,
                    'inpost_pay_payment_type' => $eventData->paymentType->value,
                    'inpost_pay_payment_updated_at' => (new DateTimeImmutable())->format('c'),
                ],
            ],
        ], $context);
    }

    private function getCurrentOrderStatus(string $orderId): array
    {
        $context = Context::createDefaultContext();
        $order = $this->shopwareOrderRepository->findOrderById($orderId, $context);

        if (!$order) {
            throw OrderNotFoundException::withId($orderId);
        }

        $transaction = $order->getTransactions()->first();
        $statusDescription = $this->buildOrderStatusDescription($order, $transaction);

        $trackingCodes = $this->orderDataExtractor->extractTrackingCodes($order);

        return [
            'order_merchant_status_description' => $statusDescription,
            'delivery_references_list' => $trackingCodes,
        ];
    }

    private function buildOrderStatusDescription(
        OrderEntity $order,
        OrderTransactionEntity $transaction,
    ): string {
        $deliveries = $order->getDeliveries();
        $totalCount = $deliveries?->count() ?? 0;

        // Multi-delivery (PartialDelivery split): prefer the shipping state over the transaction state.
        if ($totalCount > 1) {
            $primaryDelivery = $deliveries->first();
            $deliveryState = $primaryDelivery?->getStateMachineState()?->getTechnicalName() ?? '';
            $shippedCount = 0;
            foreach ($deliveries as $delivery) {
                $state = $delivery->getStateMachineState()?->getTechnicalName();
                if ($state === OrderDeliveryStates::STATE_SHIPPED
                    || $state === OrderDeliveryStates::STATE_PARTIALLY_SHIPPED
                ) {
                    ++$shippedCount;
                }
            }
            // If all deliveries are STATE_OPEN, use the transaction state (awaiting payment, etc.).
            if ($shippedCount === 0
                && $deliveryState === OrderDeliveryStates::STATE_OPEN
            ) {
                return $this->statusLabelResolver->transactionLabel($transaction->getStateMachineState()->getTechnicalName(), $order->getSalesChannelId());
            }

            return $this->statusLabelResolver->deliveryLabel($deliveryState, $shippedCount, $totalCount, $order->getSalesChannelId());
        }

        // Single-delivery flow bez zmian — preferuj transakcyjny status
        return $this->statusLabelResolver->transactionLabel($transaction->getStateMachineState()->getTechnicalName(), $order->getSalesChannelId());
    }

    private function overrideOrderAddresses(
        array &$orderData,
        string $billingAddressId,
        string $shippingAddressId,
        Context $context,
    ): void {
        $billingAddress = $this->customerMatchingService->getAddressById($billingAddressId, $context);
        $shippingAddress = ($shippingAddressId === $billingAddressId)
            ? $billingAddress
            : $this->customerMatchingService->getAddressById($shippingAddressId, $context);

        if ($billingAddress === null) {
            return;
        }

        $billingData = $this->mapAddressToOrderAddress($billingAddress);
        $orderData['addresses'] = [$billingData];
        $orderData['billingAddressId'] = $billingData['id'];

        if ($shippingAddress !== null && isset($orderData['deliveries']) && is_array($orderData['deliveries'])) {
            $shippingData = ($shippingAddressId === $billingAddressId)
                ? $billingData
                : $this->mapAddressToOrderAddress($shippingAddress);

            foreach ($orderData['deliveries'] as &$delivery) {
                $delivery['shippingOrderAddress'] = $shippingData;
                $delivery['shippingOrderAddressId'] = $shippingData['id'];
            }
        }
    }

    private function mapAddressToOrderAddress(\Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity $address): array
    {
        return [
            'id' => Uuid::randomHex(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'phoneNumber' => $address->getPhoneNumber(),
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];
    }

    /**
     * @return array{0: string, 1: string} [firstName, lastName]
     */
    private function parseDeliveryName(?string $name, string $fallbackFirst, string $fallbackLast): array
    {
        if ($name === null || trim($name) === '') {
            return [$fallbackFirst, $fallbackLast];
        }

        $parts = explode(' ', trim($name), 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? $fallbackLast;

        return [$firstName, $lastName];
    }

    /**
     * Sumuje koszty brutto wybranych usług dodatkowych (COD, PWW)
     *
     * @param string[] $deliveryCodes
     */
    private function getOptionalServicesCostGross(array $deliveryCodes, string $salesChannelId): float
    {
        $totalGross = 0.0;

        foreach ($deliveryCodes as $code) {
            try {
                $serviceCode = ServiceCode::from($code);
                $options = $this->serviceOptionsProvider->getServiceOptions($serviceCode, $salesChannelId);

                if ($options?->hasAdditionalCost()) {
                    $totalGross += $options->additionalCostGross;
                }
            } catch (ValueError) {
                continue;
            }
        }

        return $totalGross;
    }

    /**
     * Dodaje koszty usług do ceny dostawy w koszyku PRZED konwersją na zamówienie.
     * Shopware sam przeliczy VAT i wszystkie reguły.
     */
    private function applyServicesCostToCart(
        Cart $cart,
        float $servicesCostGross,
        \Shopware\Core\System\SalesChannel\SalesChannelContext $context,
    ): Cart {
        // Clear deliveries so DeliveryProcessor rebuilds them from the context shipping
        // method, not a stale method from a previous order on the same token.
        $cart->setDeliveries(new DeliveryCollection());
        $cart->removeExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS);

        // Price the base like an online InPost order: the COD method is outside shipping
        // price rules, so recalculating under it would zero the base. The COD surcharge is
        // added once below from the COD service.
        $pricingContext = $this->basePricingContextFactory->create($cart, $context);
        $cart = $this->cartService->recalculate($cart, $pricingContext);

        if ($servicesCostGross <= 0) {
            return $cart;
        }

        $currentShippingCost = 0.0;
        foreach ($cart->getDeliveries() as $delivery) {
            $currentShippingCost += $delivery->getShippingCosts()->getTotalPrice();
        }

        $newShippingGross = $currentShippingCost + $servicesCostGross;

        $newShippingPrice = new CalculatedPrice(
            $newShippingGross,
            $newShippingGross,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );

        $cart->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $newShippingPrice);

        return $this->cartService->recalculate($cart, $pricingContext);
    }

    private function restoreBaseShippingOnCart(
        Cart $cart,
        \Shopware\Core\System\SalesChannel\SalesChannelContext $context,
    ): void {
        if (!$cart->hasExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS)) {
            return;
        }

        try {
            $cart->setDeliveries(new DeliveryCollection());
            $cart->removeExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS);
            $this->cartService->recalculate($cart, $context);
        } catch (Throwable $e) {
            $this->logger->warning('InPost Pay: failed to restore base shipping on cart after order', [
                'exception' => $e,
            ]);
        }
    }
}
