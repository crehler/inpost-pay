<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Event;

use Crehler\InpostPay\Domain\Aggregate\Order;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the order payload for InPost is built (createOrder and
 * getOrder responses). Listeners may replace the payload via setPayload().
 */
class OrderPayloadBuiltEvent extends Event
{
    public function __construct(
        private readonly Order $order,
        private array $payload,
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
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
