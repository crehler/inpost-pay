<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\{DeliveryType, Money};
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

use function array_map;
use function round;

readonly class DeliveryOption
{
    public function __construct(
        public DeliveryType $deliveryType,
        public DateTimeImmutable $deliveryDate,
        public Money $deliveryPrice,
        public array $additionalOptions = [],
        public ?float $freeDeliveryMinimumGrossPrice = null,
    ) {
        $this->validate();
    }

    public function isFreeForGrossPrice(float $grossPrice): bool
    {
        if ($this->freeDeliveryMinimumGrossPrice === null) {
            return false;
        }

        return $grossPrice >= $this->freeDeliveryMinimumGrossPrice;
    }

    public function toArray(): array
    {
        $utcDate = $this->deliveryDate->setTimezone(new DateTimeZone('UTC'));

        $data = [
            'delivery_type' => $this->deliveryType->value,
            'delivery_date' => $utcDate->format('Y-m-d\TH:i:s.000\Z'),
            'delivery_price' => $this->deliveryPrice->toArray(),
        ];

        if (!empty($this->additionalOptions)) {
            $data['delivery_options'] = array_map(
                fn (DeliveryAdditionalOption $option) => $option->toArray(),
                $this->additionalOptions
            );
        }

        if ($this->freeDeliveryMinimumGrossPrice !== null) {
            $data['free_delivery_minimum_gross_price'] = round($this->freeDeliveryMinimumGrossPrice, 2);
        }

        return $data;
    }

    private function validate(): void
    {
        $now = new DateTimeImmutable();
        if ($this->deliveryDate < $now) {
            throw new InvalidArgumentException('Delivery date cannot be in the past');
        }
        foreach ($this->additionalOptions as $option) {
            if (!$option instanceof DeliveryAdditionalOption) {
                throw new InvalidArgumentException('All additional options must be DeliveryAdditionalOption instances');
            }
        }
        if ($this->freeDeliveryMinimumGrossPrice !== null && $this->freeDeliveryMinimumGrossPrice < 0) {
            throw new InvalidArgumentException('Free delivery minimum gross price cannot be negative');
        }
    }
}
