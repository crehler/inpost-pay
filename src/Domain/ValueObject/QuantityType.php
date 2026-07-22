<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum QuantityType: string
{
    case INTEGER = 'INTEGER';
    case DECIMAL = 'DECIMAL';

    public function isInteger(): bool
    {
        return $this === self::INTEGER;
    }

    public function isDecimal(): bool
    {
        return $this === self::DECIMAL;
    }
}
