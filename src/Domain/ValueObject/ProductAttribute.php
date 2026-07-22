<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

readonly class ProductAttribute
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }

    public function toArray(): array
    {
        return [
            'attribute_name' => $this->name,
            'attribute_value' => $this->value,
        ];
    }
}
