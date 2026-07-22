<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\Money;
use InvalidArgumentException;

readonly class DeliveryAdditionalOption
{
    public function __construct(
        public string $deliveryName,
        public string $deliveryCodeValue,
        public Money $deliveryOptionPrice,
    ) {
        $this->validate();
    }

    public function toArray(): array
    {
        return [
            'delivery_name' => $this->deliveryName,
            'delivery_code_value' => $this->deliveryCodeValue,
            'delivery_option_price' => $this->deliveryOptionPrice->toArray(),
        ];
    }

    private function validate(): void
    {
        if (empty($this->deliveryName)) {
            throw new InvalidArgumentException('Delivery option name cannot be empty');
        }

        if (empty($this->deliveryCodeValue)) {
            throw new InvalidArgumentException('Delivery option code value cannot be empty');
        }
    }
}
