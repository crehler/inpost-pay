<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use function array_filter;

readonly class AddressDetails
{
    public function __construct(
        public ?string $street = null,
        public ?string $building = null,
        public ?string $flat = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter(
            [
                'street' => $this->street,
                'building' => $this->building,
                'flat' => $this->flat,
            ],
            fn ($value) => $value !== null
        );
    }
}
