<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Event;

use Crehler\InpostPay\Application\Dto\CreateOrderDto;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched while creating an InPost Pay order, after the order data is fully
 * prepared (addresses overridden, invoice details applied), right before it is
 * written to the order repository. Listeners may replace it via setOrderData().
 */
class OrderDataPreparedEvent extends Event
{
    /**
     * @param array<string, mixed> $orderData
     */
    public function __construct(
        private array $orderData,
        private readonly CreateOrderDto $orderDto,
        private readonly Cart $cart,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $basketId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrderData(): array
    {
        return $this->orderData;
    }

    /**
     * @param array<string, mixed> $orderData
     */
    public function setOrderData(array $orderData): void
    {
        $this->orderData = $orderData;
    }

    public function getOrderDto(): CreateOrderDto
    {
        return $this->orderDto;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getBasketId(): string
    {
        return $this->basketId;
    }
}
