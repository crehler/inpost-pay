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

readonly class PromoCode
{
    public function __construct(
        public string $name,
        public string $promoCodeValue,
        public ?string $regulationType = null,
    ) {
        $this->validate();
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'promo_code_value' => $this->promoCodeValue,
        ];

        if ($this->regulationType !== null) {
            $data['regulation_type'] = $this->regulationType;
        }

        return $data;
    }

    private function validate(): void
    {
        if (empty($this->name)) {
            throw new InvalidArgumentException('Promo code name cannot be empty');
        }

        if (empty($this->promoCodeValue)) {
            throw new InvalidArgumentException('Promo code value cannot be empty');
        }
    }
}
