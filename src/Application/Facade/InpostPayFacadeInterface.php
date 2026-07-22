<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Facade;

use Crehler\InpostPay\Application\Dto\{BasketEventDto, CreateOrderDto, OrderEventDto, OrderUpdateNotificationDto, RefundRequestDto, RefundResponseDto, TransactionQueryDto, TransactionResponseDto, WebhookPayloadDto};
use Crehler\InpostPay\Domain\ValueObject\{WebhookResult, WidgetConfig};
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface InpostPayFacadeInterface
{
    public const INPOST_PAY_UPDATE_STATE = 'inpost_pay_update';

    public function bindBasketWithProduct(string $productId, int $quantity, SalesChannelContext $context): array;

    /**
     * Bind existing cart (with products already added) with InPost Pay
     *
     * Used for Mini Cart, Cart Summary, and Checkout pages where
     * the cart already contains products and we just need to bind it.
     *
     * @param SalesChannelContext $context Sales channel context
     *
     * @return array{basketBindingApiKey: string, basketId: string, cartItemCount: int}
     */
    public function bindBasket(SalesChannelContext $context): array;

    public function getWidgetConfig(?SalesChannelContext $context = null): WidgetConfig;

    /**
     * Retrieve current basket data for InPost Pay
     *
     * @param string $basketId Merchant basket ID
     *
     * @return array Basket data in InPost API format with summary, delivery, products, and consents
     */
    public function getBasketData(string $basketId): array;

    /**
     * Handle basket modification event from InPost Pay
     *
     * Processes events for product quantity changes, promotional codes, and related products.
     *
     * @param string         $basketId Merchant basket ID
     * @param BasketEventDto $eventDto Event data
     *
     * @return array Updated basket data in InPost API format
     */
    public function handleBasketEvent(string $basketId, BasketEventDto $eventDto): array;

    public function desynchronizeBasket(string $basketId): void;

    /**
     * Clean up local basket state without calling InPost API
     *
     * Removes the `inpost_pay_binding` extension from the cart, deletes the
     * `inpost_basket_session` record, and dispatches `BasketDesynchronizedEvent`.
     *
     * Use ONLY when InPost has already removed the binding on their side and is
     * notifying the merchant about it (incoming `DELETE /api/inpost/v1/izi/basket/{id}/binding`
     * from InPost on widget-initiated unbind or "remove basket" from the app).
     * Echoing a DELETE back to InPost in that scenario returns 500 and feeds
     * the 90% errors / 120s limiter.
     *
     * For merchant-initiated desyncs (cart deletion, empty cart, currency
     * switch, visibility-rule hide) use {@see desynchronizeBasket()} instead —
     * InPost confirmed (integracjapay@inpost.pl) that the merchant DELETE
     * is legitimate there.
     */
    public function desynchronizeBasketLocally(string $basketId): void;

    public function updateBasket(string $basketId): array;

    /**
     * Create a new order from InPost Pay order request
     *
     * @param CreateOrderDto $orderDto Order creation request
     *
     * @return array Order response in InPost API format
     */
    public function createOrder(CreateOrderDto $orderDto): array;

    /**
     * Retrieve order details by order ID
     *
     * @param string $orderId Shopware order UUID
     *
     * @return array Order data in InPost API format
     */
    public function getOrder(string $orderId): array;

    /**
     * Handle order payment status event
     *
     * Processes payment status changes from InPost Pay API and updates
     * order transaction state in Shopware
     *
     * @param string        $orderId  Shopware order UUID
     * @param OrderEventDto $eventDto Event data from InPost
     *
     * @return array Response with order status and delivery references
     */
    public function handleOrderEvent(string $orderId, OrderEventDto $eventDto): array;

    /**
     * Push an order update notification to InPost Pay (merchant → InPost).
     *
     * Sent when the merchant changes the order in Shopware (delivery shipped,
     * tracking number added, order status change, cancellation, completion).
     * Targets POST {InPostApiBase}/v1/izi/order/{orderId}/event.
     *
     * @param string                     $orderId Shopware order UUID (the id InPost holds)
     * @param OrderUpdateNotificationDto $dto     Event payload
     */
    public function pushOrderUpdate(string $orderId, OrderUpdateNotificationDto $dto): void;

    /**
     * Process incoming webhook from InPost Pay
     *
     * Validates signature and dispatches to appropriate handler
     * for payment, refund, or settlement events
     *
     * @param WebhookPayloadDto $dto Webhook payload with signature and event data
     *
     * @return WebhookResult Processing result
     */
    public function processWebhook(WebhookPayloadDto $dto): WebhookResult;

    /**
     * Fetch transactions from InPost IZI API
     *
     * Retrieves paginated list of transactions from InPost payment gateway
     * for display in admin panel.
     *
     * @param TransactionQueryDto $query Query parameters for filtering and pagination
     *
     * @return TransactionResponseDto Paginated transaction list
     */
    public function getTransactions(TransactionQueryDto $query): TransactionResponseDto;

    /**
     * Request a refund for a transaction
     *
     * Sends refund request to InPost API with generated signature.
     * This endpoint is for authorized users only.
     *
     * @param RefundRequestDto $dto Refund request data including transaction ID and amount
     *
     * @return RefundResponseDto Refund response with status and details
     */
    public function requestRefund(RefundRequestDto $dto): RefundResponseDto;
}
