<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Event;

use Crehler\InpostPay\Application\Dto\BasketEventDto;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a basket event from InPost (quantity change, promo code,
 * related product) is received, before it is applied to the cart. Listeners
 * may mutate the cart.
 */
class BasketEventReceivedEvent extends Event
{
    public function __construct(
        private readonly BasketEventDto $eventDto,
        private readonly Cart $cart,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getEventDto(): BasketEventDto
    {
        return $this->eventDto;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
