<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Event;

use Crehler\InpostPay\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

readonly class BasketRejectedEvent
{
    public function __construct(
        public string $basketId,
        public string $inpostBasketId,
        public PhoneNumber $phoneNumber,
        public DateTimeImmutable $rejectedAt,
    ) {
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->rejectedAt;
    }
}
