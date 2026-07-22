<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Consent;

enum ConsentRequirementType: string
{
    case REQUIRED_ONCE = 'REQUIRED_ONCE';
    case REQUIRED_ALWAYS = 'REQUIRED_ALWAYS';
    case OPTIONAL = 'OPTIONAL';

    public function isRequired(): bool
    {
        return $this !== self::OPTIONAL;
    }

    public function label(): string
    {
        return match ($this) {
            self::REQUIRED_ONCE => 'Required Once',
            self::REQUIRED_ALWAYS => 'Required Always',
            self::OPTIONAL => 'Optional',
        };
    }
}
