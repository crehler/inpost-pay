<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Event;

use Crehler\InpostPay\Application\Dto\OrderUpdateNotificationDto;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before an order update (status change) is pushed to InPost.
 * Listeners may replace the payload via setPayload().
 */
class OrderUpdatePayloadBuiltEvent extends Event
{
    public function __construct(
        private readonly string $orderId,
        private readonly OrderUpdateNotificationDto $notification,
        private array $payload,
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getNotification(): OrderUpdateNotificationDto
    {
        return $this->notification;
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
