<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Shopware\Core\Checkout\Cart\Cart;

readonly class ApplyPromoCodesResult
{
    public function __construct(
        public Cart $cart,
        public ?string $errorMessage = null,
    ) {
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }
}
