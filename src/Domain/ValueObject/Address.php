<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

readonly class Address
{
    public function __construct(
        public string $countryCode,
        public string $city,
        public string $postalCode,
        public string $streetLine,
        public ?AddressDetails $details = null,
        public ?string $name = null,
        public ?string $additionalAddressLine1 = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'country_code' => $this->countryCode,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'address' => $this->streetLine,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->details) {
            $data['address_details'] = $this->details->toArray();
        }

        return $data;
    }
}
