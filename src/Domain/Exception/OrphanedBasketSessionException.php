<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use Throwable;

use function sprintf;

/**
 * Thrown when a basket session still exists but its underlying Shopware cart is gone
 * (e.g. the cart was removed after an order or a context-token change). Signals 404 and
 * lets callers drop the orphaned session.
 */
class OrphanedBasketSessionException extends BasketSessionNotFoundException
{
    public static function cartNotFound(string $basketId, string $cartToken, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Basket %s is no longer available: cart %s not found', $basketId, $cartToken),
            0,
            $previous
        );
    }
}
