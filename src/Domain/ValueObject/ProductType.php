<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum ProductType: string
{
    case PRODUCT = 'PRODUCT';
    case DIGITAL = 'DIGITAL';

    public function isPhysical(): bool
    {
        return $this === self::PRODUCT;
    }

    public function isDigital(): bool
    {
        return $this === self::DIGITAL;
    }
}
