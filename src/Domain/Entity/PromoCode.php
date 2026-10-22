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
    /**
     * Name, value and regulation type of the synthetic entry that marks a product markdown as
     * covered by the Omnibus Directive. It is not a Shopware promotion - nothing in the shop
     * can add or remove it - so CartOperationService has to keep it out of the promo code
     * bookkeeping when the app echoes it back to us.
     */
    public const OMNIBUS = 'OMNIBUS';

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
