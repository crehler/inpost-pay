<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\{Money, PaymentType};
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

use function array_keys;
use function array_map;
use function array_values;
use function in_array;
use function is_string;
use function sprintf;

readonly class BasketSummary
{
    public function __construct(
        public Money $basketBasePrice,
        public string $currency,
        public array $paymentTypes,
        public ?Money $basketFinalPrice = null,
        public ?Money $basketPromoPrice = null,
        public bool $freeBasket = false,
        public ?DateTimeImmutable $basketExpirationDate = null,
        public ?string $basketAdditionalInformation = null,
        public ?BasketNotice $basketNotice = null,
        public ?array $additionalParameters = null,
    ) {
        $this->validate();
    }

    public function isExpired(): bool
    {
        if ($this->basketExpirationDate === null) {
            return false;
        }

        return new DateTimeImmutable() > $this->basketExpirationDate;
    }

    public function getEffectivePrice(): Money
    {
        if ($this->basketFinalPrice !== null) {
            return $this->basketFinalPrice;
        }

        if ($this->basketPromoPrice !== null) {
            return $this->basketPromoPrice;
        }

        return $this->basketBasePrice;
    }

    public function toArray(): array
    {
        $data = [
            'basket_base_price' => $this->basketBasePrice->toArray(),
            'currency' => $this->currency,
            'payment_type' => array_map(fn (PaymentType $type) => $type->value, $this->paymentTypes),
        ];

        if ($this->basketPromoPrice !== null) {
            $data['basket_promo_price'] = $this->basketPromoPrice->toArray();
        }

        if ($this->basketFinalPrice !== null) {
            $data['basket_final_price'] = $this->basketFinalPrice->toArray();
        }

        if ($this->freeBasket) {
            $data['free_basket'] = true;
        }

        if ($this->basketExpirationDate !== null) {
            $utcDate = $this->basketExpirationDate->setTimezone(new DateTimeZone('UTC'));
            $data['basket_expiration_date'] = $utcDate->format('Y-m-d\TH:i:s.000\Z');
        }

        if ($this->basketAdditionalInformation !== null) {
            $data['basket_additional_information'] = $this->basketAdditionalInformation;
        }

        if ($this->basketNotice !== null) {
            $data['basket_notice'] = $this->basketNotice->toArray();
        }

        if ($this->additionalParameters !== null && !empty($this->additionalParameters)) {
            $data['basket_additional_parameters'] = array_map(
                fn ($key, $value) => ['key' => $key, 'value' => $value],
                array_keys($this->additionalParameters),
                array_values($this->additionalParameters)
            );
        }

        return $data;
    }

    private function validate(): void
    {
        if (!in_array($this->currency, ['PLN'], true)) {
            throw new InvalidArgumentException(sprintf('Currency %s is not supported. Currently only PLN is supported.', $this->currency));
        }
        if (empty($this->paymentTypes)) {
            throw new InvalidArgumentException('At least one payment type must be specified');
        }
        foreach ($this->paymentTypes as $paymentType) {
            if (!$paymentType instanceof PaymentType) {
                throw new InvalidArgumentException('All payment types must be PaymentType enum instances');
            }
        }
        if ($this->basketExpirationDate !== null) {
            $now = new DateTimeImmutable();
            if ($this->basketExpirationDate < $now) {
                throw new InvalidArgumentException('Basket expiration date cannot be in the past');
            }
        }
        if ($this->additionalParameters !== null) {
            foreach ($this->additionalParameters as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidArgumentException('Additional parameters must be key-value pairs of strings');
                }
            }
        }
    }
}
