<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Event;

use Crehler\InpostPay\Domain\Entity\BasketSummary;
use Crehler\InpostPay\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

readonly class BasketConfirmedEvent
{
    public function __construct(
        public string $basketId,
        public string $inpostBasketId,
        public PhoneNumber $phoneNumber,
        public BasketSummary $basketSummary,
        public array $deliveryOptions,
        public array $products,
        public DateTimeImmutable $confirmedAt,
    ) {
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->confirmedAt;
    }
}
