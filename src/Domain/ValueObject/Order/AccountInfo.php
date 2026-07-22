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

readonly class AccountInfo
{
    public function __construct(
        public string $name,
        public string $surname,
        public string $mail,
        public PhoneNumber $phoneNumber,
        public Address $clientAddress,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'surname' => $this->surname,
            'mail' => $this->mail,
            'phone_number' => $this->phoneNumber->toArray(),
            'client_address' => $this->clientAddress->toArray(),
        ];
    }
}
