<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Facade;

use Crehler\InpostPay\Application\Dto\{BasketEventDto, CreateOrderDto, OrderEventDto, OrderUpdateNotificationDto, RefundRequestDto, RefundResponseDto, TransactionQueryDto, TransactionResponseDto, WebhookPayloadDto};
use Crehler\InpostPay\Application\Event\OrderUpdatePayloadBuiltEvent;
use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Application\Service\{BasketService, CartOperationService, InpostBasketSessionService, InpostPayAuthenticator, OrderService, RefundService, WebhookService};
use Crehler\InpostPay\Domain\Event\BasketDesynchronizedEvent;
use Crehler\InpostPay\Domain\Exception\{BasketNotFoundException, EmptyCartException, InpostPayEndpointException, InvalidBasketException, InvalidOrderEventException, InvalidOrderException, InvalidWebhookSignatureException, OrderNotFoundException, UnsupportedWebhookEventException};
use Crehler\InpostPay\Domain\ValueObject\Analytics\BasketAnalytics;
use Crehler\InpostPay\Domain\ValueObject\{WebhookResult, WidgetConfig};
use Crehler\InpostPay\Infrastructure\Api\Builder\InpostApiResponseBuilder;
use Crehler\InpostPay\Infrastructure\Client\InpostPayClient;
use Crehler\InpostPay\Infrastructure\Logger\ExceptionLogger;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use DateTimeImmutable;
use DomainException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function get_class;
use function is_array;
use function json_decode;
use function sprintf;

final readonly class InpostPayFacade implements InpostPayFacadeInterface
{
    public function __construct(
        private InpostPayClient $client,
        private InpostPayAuthenticator $authenticator,
        private InpostPayConfigProvider $configProvider,
        private CartService $cartService,
        private CartPersister $cartPersister,
        private InpostBasketSessionService $basketSessionService,
        private BasketService $basketService,
        private OrderService $orderService,
        private CartOperationService $cartOperationService,
        private InpostApiResponseBuilder $responseBuilder,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private ExceptionLogger $exceptionLogger,
        private WebhookService $webhookService,
        private RefundService $refundService,
    ) {
    }

    public function getWidgetConfig(?SalesChannelContext $context = null): WidgetConfig
    {
        return $this->configProvider->getWidgetConfig($context);
    }

    public function getBasketData(string $basketId): array
    {
        $this->logger->debug('Loading basket data for InPost', ['basket_id' => $basketId]);

        try {
            $basket = $this->basketService->loadBasketData($basketId);

            return $this->responseBuilder->buildConfirmationResponse($basket);
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Failed to get basket data', $e, [
                'basket_id' => $basketId,
            ]);
            throw $e;
        }
    }

    public function bindBasketWithProduct(
        string $productId,
        int $quantity,
        SalesChannelContext $context,
        ?Request $request = null,
    ): array {
        $this->logger->debug('Binding basket with a single product', [
            'basket_id' => $context->getToken(),
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);

        try {
            $context->getContext()->addState(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE);

            $cart = $this->cartService->getCart($context->getToken(), $context);

            $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $quantity);
            $lineItem->setStackable(true);
            $lineItem->setRemovable(true);

            $cart = $this->cartService->add(cart: $cart, items: $lineItem, context: $context);

            $basketId = $context->getToken();

            $auth = $this->authenticator->authenticate();

            $config = $this->getWidgetConfig($context);

            $basketBindingApiKey = $this->client->bindBasket(
                basketId: $basketId,
                bearerToken: $auth->token,
                mode: $config->mode
            );

            $analytics = $this->extractAnalyticsFromRequest($request);

            $this->basketSessionService->findOrCreateSession(
                basketId: $basketId,
                salesChannelId: $context->getSalesChannel()->getId(),
                basketBindingApiKey: $basketBindingApiKey,
                analytics: $analytics,
            );

            $cart->addExtension('inpost_pay_binding', new ArrayEntity([
                'basket_binding_api_key' => $basketBindingApiKey,
                'basket_id' => $basketId,
                'bound_at' => new DateTimeImmutable(),
            ]));

            $this->cartPersister->save($cart, $context);

            return [
                'basketBindingApiKey' => $basketBindingApiKey,
                'basketId' => $basketId,
                'cartItemCount' => $cart->getLineItems()->count(),
            ];
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Failed to bind basket with product', $e, [
                'product_id' => $productId,
                'quantity' => $quantity,
                'basket_id' => $context->getToken(),
            ]);
            throw $e;
        }
    }

    public function bindBasket(SalesChannelContext $context, ?Request $request = null): array
    {
        $basketId = $context->getToken();

        $this->logger->debug('Binding the current cart as a basket', ['basket_id' => $basketId]);

        $cart = $this->cartService->getCart($basketId, $context);

        if ($cart->getLineItems()->count() === 0) {
            throw EmptyCartException::forBasket($basketId);
        }

        $auth = $this->authenticator->authenticate();
        $config = $this->getWidgetConfig($context);

        $basketBindingApiKey = $this->client->bindBasket(
            basketId: $basketId,
            bearerToken: $auth->token,
            mode: $config->mode
        );

        $analytics = $this->extractAnalyticsFromRequest($request);

        $this->basketSessionService->findOrCreateSession(
            basketId: $basketId,
            salesChannelId: $context->getSalesChannel()->getId(),
            basketBindingApiKey: $basketBindingApiKey,
            analytics: $analytics,
        );

        $cart->addExtension('inpost_pay_binding', new ArrayEntity([
            'basket_binding_api_key' => $basketBindingApiKey,
            'basket_id' => $basketId,
            'bound_at' => new DateTimeImmutable(),
        ]));

        $this->cartPersister->save($cart, $context);

        return [
            'basketBindingApiKey' => $basketBindingApiKey,
            'basketId' => $basketId,
            'cartItemCount' => $cart->getLineItems()->count(),
        ];
    }

    public function handleBasketEvent(string $basketId, BasketEventDto $eventDto): array
    {
        $this->logger->debug('Handling basket event', [
            'basket_id' => $basketId,
            'event_type' => $eventDto->eventType->value,
        ]);

        try {
            $modifiedBasket = $this->basketService->handleBasketEvent($basketId, $eventDto);

            return $this->responseBuilder->buildConfirmationResponse($modifiedBasket);
        } catch (Throwable $e) {
            $this->logger->error('Failed to handle basket event', [
                'basket_id' => $basketId,
                'event_type' => $eventDto->eventType->value,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            $this->exceptionLogger->error('Failed to handle basket event', $e, [
                'basket_id' => $basketId,
                'event_type' => $eventDto->eventType->value,
            ]);

            throw $e;
        }
    }

    public function desynchronizeBasket(string $basketId): void
    {
        $this->logger->debug('Desynchronizing basket from InPost', ['basket_id' => $basketId]);

        try {
            $session = $this->basketSessionService->getSessionByBasketId($basketId);
            if (!$session) {
                throw new BasketNotFoundException(sprintf('Basket session not found for basketId: %s', $basketId));
            }

            $salesChannelId = $session->getSalesChannelId();

            // InPost knows the basket only by its immutable basketId from bind time.
            // The caller may pass the current Shopware cart token, which diverges after a
            // context-token change (guest login), so always resolve the InPost-facing id.
            $inpostBasketId = $session->getBasketId();

            try {
                $auth = $this->authenticator->authenticate();
                $config = $this->getWidgetConfig();

                $this->client->deleteBasketBinding(
                    basketId: $inpostBasketId,
                    bearerToken: $auth->token,
                    mode: $config->mode,
                    ifBasketRealized: false
                );

                $this->logger->info('Basket binding deleted in InPost API', [
                    'basket_id' => $inpostBasketId,
                    'if_basket_realized' => false,
                ]);
            } catch (Throwable $e) {
                $this->exceptionLogger->warning('Failed to delete basket binding in InPost API', $e, [
                    'basket_id' => $basketId,
                ]);
            }

            $this->cleanupLocalBasketState($basketId, $salesChannelId);
        } catch (BasketNotFoundException|InvalidBasketException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Unexpected error during basket desynchronization', $e, [
                'basket_id' => $basketId,
            ]);
            throw new DomainException(sprintf('Failed to desynchronize basket %s: %s', $basketId, $e->getMessage()), previous: $e);
        }
    }

    public function desynchronizeBasketLocally(string $basketId): void
    {
        $this->logger->debug('Cleaning up local basket state only, not notifying InPost', ['basket_id' => $basketId]);

        try {
            $session = $this->basketSessionService->getSessionByBasketId($basketId);
            if (!$session) {
                throw new BasketNotFoundException(sprintf('Basket session not found for basketId: %s', $basketId));
            }

            $this->cleanupLocalBasketState($basketId, $session->getSalesChannelId());
        } catch (BasketNotFoundException|InvalidBasketException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Unexpected error during local basket cleanup', $e, [
                'basket_id' => $basketId,
            ]);
            throw new DomainException(sprintf('Failed to clean up local basket state %s: %s', $basketId, $e->getMessage()), previous: $e);
        }
    }

    /**
     * Wysyła zaktualizowane dane koszyka do InPost i zwraca odpowiedź API.
     *
     * Metoda pobiera powiązaną sesję koszyka i używa niezmiennego identyfikatora koszyka z sesji przy wywołaniu aktualizacji po stronie InPost.
     *
     * @param string $basketId Identyfikator koszyka używany do odnalezienia lokalnej sesji (np. token Shopware).
     *
     * @throws BasketNotFoundException jeśli nie istnieje sesja powiązana z podanym identyfikatorem koszyka
     *
     * @return array tablica zawierająca odpowiedź API InPost z informacjami o zaktualizowanym stanie koszyka
     */
    public function updateBasket(string $basketId): array
    {
        $this->logger->debug('Updating basket in InPost', ['basket_id' => $basketId]);

        try {
            $session = $this->basketSessionService->getSessionByBasketId($basketId);
            if (!$session) {
                throw new BasketNotFoundException(sprintf('Basket session not found for basketId: %s', $basketId));
            }

            $basket = $this->basketService->loadBasketData($basketId);
            $basketData = $this->responseBuilder->buildConfirmationResponse($basket);

            $auth = $this->authenticator->authenticate();
            $config = $this->getWidgetConfig();

            // Address InPost by the immutable basketId, not the (possibly migrated) cart token.
            return $this->client->updateBasket(
                basketId: $session->getBasketId(),
                data: $basketData,
                bearerToken: $auth->token,
                mode: $config->mode
            );
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Failed to update basket in InPost Pay', $e, [
                'basket_id' => $basketId,
            ]);
            throw $e;
        }
    }

    public function createOrder(CreateOrderDto $orderDto): array
    {
        $this->logger->debug('Creating order from basket', [
            'basket_id' => $orderDto->orderDetails->basketId,
        ]);

        try {
            $order = $this->orderService->createOrder($orderDto);
            $response = $this->responseBuilder->buildOrderResponse($order);

            $basketId = $orderDto->orderDetails->basketId;
            try {
                $session = $this->basketSessionService->getSessionByBasketId($basketId);
                if ($session) {
                    $cartToken = $session->getCartToken() ?? $basketId;
                    $context = $this->cartOperationService->createSalesChannelContextForSession($session);
                    // Add state to prevent CartDesynchronizationSubscriber from triggering during cart deletion
                    $context->getContext()->addState(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE);
                    $this->cartPersister->delete($cartToken, $context);

                    $this->logger->info('Cart deleted after order creation', [
                        'basket_id' => $basketId,
                    ]);
                }
            } catch (Throwable $e) {
                $this->exceptionLogger->warning('Failed to delete cart after order creation', $e, [
                    'basket_id' => $basketId,
                ]);
            }

            $this->logger->info('Order created successfully via InPost Pay', [
                'order_id' => $response['order_details']['order_id'] ?? 'unknown',
                'basket_id' => $orderDto->orderDetails->basketId,
            ]);

            return $response;
        } catch (InvalidBasketException|InvalidOrderException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Failed to create order via InPost Pay', $e, [
                'basket_id' => $orderDto->orderDetails->basketId,
            ]);
            throw new DomainException(sprintf('Failed to create order: %s', $e->getMessage()), previous: $e);
        }
    }

    public function getOrder(string $orderId): array
    {
        $this->logger->debug('Loading order details', ['order_id' => $orderId]);

        try {
            $order = $this->orderService->getOrderById($orderId);

            return $this->responseBuilder->buildOrderResponse($order);
        } catch (OrderNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Failed to retrieve order', $e, [
                'order_id' => $orderId,
            ]);
            throw new DomainException(sprintf('Failed to retrieve order: %s', $e->getMessage()), previous: $e);
        }
    }

    public function handleOrderEvent(string $orderId, OrderEventDto $eventDto): array
    {
        $this->logger->debug('Handling order event', [
            'order_id' => $orderId,
            'event_id' => $eventDto->eventId,
        ]);

        try {
            $this->logger->info('Handling order event via facade', [
                'order_id' => $orderId,
                'event_id' => $eventDto->eventId,
            ]);

            $response = $this->orderService->handleOrderEvent($orderId, $eventDto);

            $this->logger->info('Order event handled successfully', [
                'order_id' => $orderId,
                'event_id' => $eventDto->eventId,
            ]);

            return $response;
        } catch (OrderNotFoundException|InvalidOrderEventException $e) {
            $this->exceptionLogger->error('Order event handling failed', $e, [
                'order_id' => $orderId,
                'event_id' => $eventDto->eventId,
            ]);
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Unexpected error handling order event', $e, [
                'order_id' => $orderId,
                'event_id' => $eventDto->eventId,
            ]);
            throw new DomainException(sprintf('Failed to handle order event: %s', $e->getMessage()), previous: $e);
        }
    }

    public function pushOrderUpdate(string $orderId, OrderUpdateNotificationDto $dto): void
    {
        $this->logger->debug('Pushing order update to InPost', [
            'order_id' => $orderId,
            'event_id' => $dto->eventId,
        ]);

        try {
            $auth = $this->authenticator->authenticate();
            $config = $this->getWidgetConfig();

            $event = new OrderUpdatePayloadBuiltEvent($orderId, $dto, $dto->toArray());
            $this->eventDispatcher->dispatch($event);

            $this->client->sendOrderEvent(
                orderId: $orderId,
                data: $event->getPayload(),
                bearerToken: $auth->token,
                mode: $config->mode,
            );
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Failed to push order update to InPost Pay', $e, [
                'order_id' => $orderId,
                'event_id' => $dto->eventId,
            ]);
            throw $e;
        }
    }

    public function processWebhook(WebhookPayloadDto $dto): WebhookResult
    {
        $this->logger->debug('Processing webhook payload', [
            'event_type' => $dto->eventType->value,
            'api_version' => $dto->apiVersion,
        ]);

        try {
            $this->logger->info('Processing webhook via facade', [
                'event_type' => $dto->eventType->value,
                'api_version' => $dto->apiVersion,
            ]);

            $result = $this->webhookService->processWebhook($dto);

            $this->logger->info('Webhook processed successfully', [
                'event_type' => $dto->eventType->value,
                'status' => $result->status,
            ]);

            return $result;
        } catch (InvalidWebhookSignatureException|UnsupportedWebhookEventException $e) {
            $this->exceptionLogger->warning('Webhook processing failed', $e, [
                'event_type' => $dto->eventType->value,
            ]);
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Unexpected error processing webhook', $e, [
                'event_type' => $dto->eventType->value,
            ]);
            throw new DomainException(sprintf('Failed to process webhook: %s', $e->getMessage()), previous: $e);
        }
    }

    public function getTransactions(TransactionQueryDto $query): TransactionResponseDto
    {
        $this->logger->debug('Fetching transactions from InPost via facade', [
            'page' => $query->page,
            'order_id' => $query->orderId,
        ]);

        try {
            $this->logger->info('Fetching transactions from InPost API', [
                'page' => $query->page,
                'per_page' => $query->perPage,
                'order_id' => $query->orderId,
            ]);

            $auth = $this->authenticator->authenticate();
            $config = $this->getWidgetConfig();

            return $this->client->getTransactions(
                query: $query,
                bearerToken: $auth->token,
                mode: $config->mode,
            );
        } catch (InpostPayEndpointException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Unexpected error fetching transactions', $e, [
                'page' => $query->page,
                'per_page' => $query->perPage,
                'order_id' => $query->orderId,
            ]);
            throw new DomainException(sprintf('Failed to fetch transactions: %s', $e->getMessage()), previous: $e);
        }
    }

    public function requestRefund(RefundRequestDto $dto): RefundResponseDto
    {
        $this->logger->debug('Processing refund request via facade', [
            'transaction_id' => $dto->transactionId,
        ]);

        try {
            $this->logger->info('Processing refund request via facade', [
                'transaction_id' => $dto->transactionId,
                'refund_amount' => $dto->refundAmount,
            ]);

            $result = $this->refundService->requestRefund($dto);

            $this->logger->info('Refund request processed successfully', [
                'transaction_id' => $dto->transactionId,
                'status' => $result->status,
            ]);

            return $result;
        } catch (InpostPayEndpointException $e) {
            $this->exceptionLogger->error('Refund request failed', $e, [
                'transaction_id' => $dto->transactionId,
                'refund_amount' => $dto->refundAmount,
            ]);
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Unexpected error processing refund request', $e, [
                'transaction_id' => $dto->transactionId,
                'refund_amount' => $dto->refundAmount,
            ]);
            throw new DomainException(sprintf('Failed to process refund request: %s', $e->getMessage()), previous: $e);
        }
    }

    private function cleanupLocalBasketState(string $basketId, ?string $salesChannelId): void
    {
        try {
            $cart = $this->cartOperationService->loadCartByBasketId($basketId, [InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE]);
            if ($cart->hasExtension('inpost_pay_binding')) {
                $cart->removeExtension('inpost_pay_binding');
            }
        } catch (InvalidBasketException $e) {
            throw $e;
        }

        $this->basketSessionService->deleteSessionByBasketId($basketId);

        $event = new BasketDesynchronizedEvent(
            basketId: $basketId,
            salesChannelId: $salesChannelId,
            desynchronizedAt: new DateTimeImmutable(),
        );
        $this->eventDispatcher->dispatch($event);

        $this->logger->info('Basket desynchronized successfully', [
            'basket_id' => $basketId,
            'sales_channel_id' => $salesChannelId,
        ]);
    }

    private function extractAnalyticsFromRequest(?Request $request): ?BasketAnalytics
    {
        if ($request === null) {
            return null;
        }

        $content = $request->getContent();
        if (empty($content)) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['analytics'])) {
            return null;
        }

        $analyticsData = $data['analytics'];
        if (!is_array($analyticsData)) {
            return null;
        }

        return BasketAnalytics::fromArray($analyticsData);
    }
}
