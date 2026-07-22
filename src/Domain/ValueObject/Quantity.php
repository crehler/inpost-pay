<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

readonly class Quantity
{
    public function __construct(
        public float $value,
        public string $unit,
        public QuantityType $type,
    ) {
    }

    public function toArray(): array
    {
        return [
            'quantity' => $this->value,
            'quantity_unit' => $this->unit,
            'quantity_type' => $this->type->value,
        ];
    }
}
