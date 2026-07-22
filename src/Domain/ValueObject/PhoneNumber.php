<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use InvalidArgumentException;

use function preg_match;
use function sprintf;

readonly class PhoneNumber
{
    public function __construct(
        public string $countryPrefix,
        public string $phone,
    ) {
        $this->validate();
    }

    public function __toString(): string
    {
        return $this->getFullNumber();
    }

    public function getFullNumber(): string
    {
        return $this->countryPrefix . $this->phone;
    }

    public function toArray(): array
    {
        return [
            'country_prefix' => $this->countryPrefix,
            'phone' => $this->phone,
        ];
    }

    private function validate(): void
    {
        if (empty($this->countryPrefix)) {
            throw new InvalidArgumentException('Country prefix cannot be empty');
        }

        if (empty($this->phone)) {
            throw new InvalidArgumentException('Phone number cannot be empty');
        }

        if (!preg_match('/^\+\d{1,4}$/', $this->countryPrefix)) {
            throw new InvalidArgumentException(sprintf('Invalid country prefix format: %s (expected +XX format)', $this->countryPrefix));
        }

        if (!preg_match('/^[\d\s\-]+$/', $this->phone)) {
            throw new InvalidArgumentException(sprintf('Invalid phone number format: %s (only digits, spaces, and dashes allowed)', $this->phone));
        }
    }
}
