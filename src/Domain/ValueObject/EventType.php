<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum EventType: string
{
    case PRODUCTS_QUANTITY = 'PRODUCTS_QUANTITY';
    case PROMO_CODES = 'PROMO_CODES';
    case RELATED_PRODUCTS = 'RELATED_PRODUCTS';

    public function isQuantityChange(): bool
    {
        return $this === self::PRODUCTS_QUANTITY;
    }

    public function isPromoCode(): bool
    {
        return $this === self::PROMO_CODES;
    }

    public function isRelatedProduct(): bool
    {
        return $this === self::RELATED_PRODUCTS;
    }
}
