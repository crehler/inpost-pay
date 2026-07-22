<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Event;

use Crehler\InpostPay\Domain\ValueObject\DeliveryType;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched while building an InPost Pay order, after the cart is loaded and
 * the SalesChannelContext (with the shipping method mapped from the InPost
 * delivery type) is built, but before the cart is recalculated and converted
 * to an order.
 *
 * Listeners may mutate the cart so that the upcoming recalculation reflects the
 * InPost delivery choice. The plugin itself stays agnostic of any third-party
 * cart customisation; project-specific glue lives in the listeners.
 */
class OrderCartPreparedEvent extends Event
{
    public function __construct(
        private readonly Cart $cart,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly DeliveryType $deliveryType,
        private readonly string $basketId,
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getDeliveryType(): DeliveryType
    {
        return $this->deliveryType;
    }

    public function getBasketId(): string
    {
        return $this->basketId;
    }
}
