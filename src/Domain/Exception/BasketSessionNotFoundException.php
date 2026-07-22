<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use function sprintf;

class BasketSessionNotFoundException extends InvalidBasketException
{
    public static function forBasketId(string $basketId): self
    {
        return new self(
            sprintf('Basket session not found for basketId: %s', $basketId)
        );
    }
}
