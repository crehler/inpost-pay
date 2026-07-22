<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use InvalidArgumentException;

readonly class ProductAttribute
{
    public function __construct(
        public string $attributeName,
        public string $attributeValue,
    ) {
        $this->validate();
    }

    public function toArray(): array
    {
        return [
            'attribute_name' => $this->attributeName,
            'attribute_value' => $this->attributeValue,
        ];
    }

    private function validate(): void
    {
        if (empty($this->attributeName)) {
            throw new InvalidArgumentException('Attribute name cannot be empty');
        }

        if (empty($this->attributeValue)) {
            throw new InvalidArgumentException('Attribute value cannot be empty');
        }
    }
}
