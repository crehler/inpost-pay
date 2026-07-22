<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Order;

use Crehler\InpostPay\Domain\ValueObject\Money;

readonly class OrderPricing
{
    public function __construct(
        public Money $base,
        public Money $delivery,
        public Money $final,
        public ?float $discount = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'base' => $this->base->toArray(),
            'delivery' => $this->delivery->toArray(),
            'final' => $this->final->toArray(),
        ];

        if ($this->discount !== null) {
            $data['discount'] = $this->discount;
        }

        return $data;
    }
}
