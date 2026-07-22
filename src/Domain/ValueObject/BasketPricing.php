<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

readonly class BasketPricing
{
    public function __construct(
        public Money $base,
        public Money $promo,
        public Money $final,
    ) {
    }
}
