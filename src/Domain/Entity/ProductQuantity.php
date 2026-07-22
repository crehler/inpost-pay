<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\QuantityType;
use InvalidArgumentException;

readonly class ProductQuantity
{
    /**
     * @var string
     */
    public const PIECE = 'pcs';

    public function __construct(
        public int $quantity,
        public QuantityType $quantityType,
        public ?string $quantityUnit = null,
        public ?int $availableQuantity = null,
        public ?int $minQuantity = null,
        public ?int $maxQuantity = null,
        public ?int $quantityJump = null,
    ) {
        $this->validate();
    }

    public function isInteger(): bool
    {
        return $this->quantityType->isInteger();
    }

    public function isDecimal(): bool
    {
        return $this->quantityType->isDecimal();
    }

    public function toArray(): array
    {
        $data = [
            'quantity' => $this->quantity,
            'quantity_type' => $this->quantityType->value,
        ];

        if ($this->quantityUnit !== null) {
            $data['quantity_unit'] = $this->quantityUnit;
        }

        if ($this->availableQuantity !== null) {
            $data['available_quantity'] = $this->availableQuantity;
        }

        if ($this->minQuantity !== null) {
            $data['min_quantity'] = $this->minQuantity;
        }

        if ($this->maxQuantity !== null) {
            $data['max_quantity'] = $this->maxQuantity;
        }

        if ($this->quantityJump !== null) {
            $data['quantity_jump'] = $this->quantityJump;
        }

        return $data;
    }

    private function validate(): void
    {
        if ($this->quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero');
        }

        if ($this->availableQuantity !== null && $this->availableQuantity < 0) {
            throw new InvalidArgumentException('Available quantity cannot be negative');
        }

        if ($this->minQuantity !== null && $this->minQuantity < 0) {
            throw new InvalidArgumentException('Minimum quantity cannot be negative');
        }

        if ($this->maxQuantity !== null && $this->maxQuantity < 0) {
            throw new InvalidArgumentException('Maximum quantity cannot be negative');
        }

        if ($this->minQuantity !== null && $this->maxQuantity !== null && $this->minQuantity > $this->maxQuantity) {
            throw new InvalidArgumentException('Minimum quantity cannot be greater than maximum quantity');
        }

        if ($this->quantityJump !== null && $this->quantityJump <= 0) {
            throw new InvalidArgumentException('Quantity jump must be greater than zero');
        }
    }
}
