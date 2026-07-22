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
use Crehler\InpostPay\Domain\Service\OrderStatusDescriptionMapper;
use Crehler\InpostPay\Domain\ValueObject\{DeliveryType, KeyValue, PaymentStatus, PaymentType, ServiceCode};
use Crehler\InpostPay\Domain\ValueObject\Order\CustomerInfo;
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
        private OrderTransactionStateHandler $transactionStateHandler,
        private ServiceOptionsProvider $serviceOptionsProvider,
        private DeliveryMappingProvider $deliveryMappingProvider,
        private ParcelLockerAddressProvider $parcelLockerAddressProvider,
        private EventDispatcherInterface $eventDispatcher,
        private BasePricingContextFactory $basePricingContextFactory,
        private LoggerInterface $logger,
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

            $context = $this->cartOperationService->createSalesChannelContextWithCustomer(
                $cartToken,
                $salesChannelId,
                $customer->getId(),
                $inpostPayMethodId,
                $shippingMethodId,
            );

            $cart = $this->cartOperationService->loadCartByBasketId($basketId);

            // Give listeners a chance to adjust the cart for the chosen InPost delivery
            // before we recalculate and convert it to an order (e.g. clearing shipping
            // method cache held by partial-delivery plugins).
            $this->eventDispatcher->dispatch(
                new OrderCartPreparedEvent($cart, $context, $orderDto->delivery->deliveryType, $basketId)
            );

            // Dodaj koszty usług COD/PWW do ceny dostawy
            $deliveryCodes = $orderDto->delivery->deliveryCodes ?? [];
            $servicesCostGross = $this->getOptionalServicesCostGross($deliveryCodes, $salesChannelId);
            $cart = $this->applyServicesCostToCart($cart, $servicesCostGross, $context);

            $conversionContext = (new OrderConversionContext())
                ->setIncludeCustomer(true)
                ->setIncludeBillingAddress(true)
                ->setIncludeDeliveries(true)
                ->setIncludeTransactions(true)
                ->setIncludePersistentData(true);

            $orderData = $this->orderConverter->convertToOrder($cart, $context, $conversionContext);
            $shopwareOrderId = $orderData['id'];

            $this->overrideOrderAddresses($orderData, $billingAddressId, $shippingAddressId, $tempContext->getContext());

            if ($orderDto->orderDetails->orderComments) {
                $orderData['customerComment'] = $orderDto->orderDetails->orderComments;
            }

            if ($orderDto->delivery->mail !== null && $orderDto->delivery->mail !== '') {
                $orderData['orderCustomer']['email'] = $orderDto->delivery->mail;
            }

            $this->orderRepository->create([$orderData], $context->getContext());

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

            $statusMapper = new OrderStatusDescriptionMapper();
            $statusDescription = $statusMapper->mapToPolish($status);

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
                        $context,
                        $createdOrder,
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
        $statusMapper = new OrderStatusDescriptionMapper();
        $polishDescription = $statusMapper->mapPaymentStatusToPolish($eventData->paymentStatus);

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
        $statusMapper = new OrderStatusDescriptionMapper();
        $deliveries = $order->getDeliveries();
        $totalCount = $deliveries?->count() ?? 0;

        // Multi-delivery (PartialDelivery split): preferuj status wysyłki nad transakcyjnym
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
            // Jeśli wszystkie deliveries są w STATE_OPEN, użyj statusu transakcji (oczekuje na płatność itp.)
            if ($shippedCount === 0
                && $deliveryState === OrderDeliveryStates::STATE_OPEN
            ) {
                return $statusMapper->mapToPolish($transaction->getStateMachineState()->getTechnicalName());
            }

            return $statusMapper->mapDeliveryStateToPolish($deliveryState, $shippedCount, $totalCount);
        }

        // Single-delivery flow bez zmian — preferuj transakcyjny status
        return $statusMapper->mapToPolish($transaction->getStateMachineState()->getTechnicalName());
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
        // Załadowany koszyk może wciąż trzymać delivery z poprzedniego zamówienia na
        // tym samym basket (ten sam token), a recalculate() nie nadpisuje metody
        // wysyłki na istniejącym delivery. Czyścimy deliveries, by DeliveryProcessor
        // zbudował je od nowa z metody ustawionej w kontekście (zmapowanej z typu
        // dostawy InPost), zamiast dziedziczyć metodę z poprzedniego zamówienia.
        $cart->setDeliveries(new DeliveryCollection());
        $cart->removeExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS);

        // Wyceń bazę jak dla zamówienia InPost online: metoda COD jest celowo poza
        // regułami cenowymi wysyłki, więc recalculate pod nią wyzerowałby bazę. Dopłatę
        // pobrania dokładamy poniżej z usługi COD (raz) - SUEZ-1045.
        $pricingContext = $this->basePricingContextFactory->create($cart, $context);
        $cart = $this->cartService->recalculate($cart, $pricingContext);

        if ($servicesCostGross <= 0) {
            return $cart;
        }

        // Pobierz aktualną cenę dostawy z koszyka
        $currentShippingCost = 0.0;
        foreach ($cart->getDeliveries() as $delivery) {
            $currentShippingCost += $delivery->getShippingCosts()->getTotalPrice();
        }

        // Nowa cena = bazowa + usługi
        $newShippingGross = $currentShippingCost + $servicesCostGross;

        // Utwórz CalculatedPrice (Shopware przeliczy VAT przy recalculate)
        $newShippingPrice = new CalculatedPrice(
            $newShippingGross,
            $newShippingGross,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );

        // Dodaj extension - Shopware użyje tej ceny przy recalculate
        $cart->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $newShippingPrice);

        // Przelicz koszyk - Shopware sam obliczy VAT z shipping method
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
