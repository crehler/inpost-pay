<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Event;

use DateTimeImmutable;

readonly class BasketDesynchronizedEvent
{
    public function __construct(
        public string $basketId,
        public string $salesChannelId,
        public DateTimeImmutable $desynchronizedAt,
    ) {
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->desynchronizedAt;
    }
}
