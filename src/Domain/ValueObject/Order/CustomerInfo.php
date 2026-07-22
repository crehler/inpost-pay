<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Order;

use Crehler\InpostPay\Domain\ValueObject\{Address, PhoneNumber};

readonly class CustomerInfo
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public PhoneNumber $phone,
        public Address $address,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->firstName,
            'surname' => $this->lastName,
            'mail' => $this->email,
            'phone_number' => $this->phone->toArray(),
            'client_address' => $this->address->toArray(),
        ];
    }
}
