<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Event;

use Crehler\InpostPay\Domain\Aggregate\InpostBasket;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after any basket payload for InPost is built (confirmation, GET
 * basket, basket-event response, PUT update), before it is sent or returned.
 * Listeners may replace the payload via setPayload().
 */
class BasketPayloadBuiltEvent extends Event
{
    public function __construct(
        private readonly InpostBasket $basket,
        private array $payload,
    ) {
    }

    public function getBasket(): InpostBasket
    {
        return $this->basket;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }
}
