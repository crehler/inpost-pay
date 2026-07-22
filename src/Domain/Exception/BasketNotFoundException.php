<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use InvalidArgumentException;

use function sprintf;

class BasketNotFoundException extends InvalidArgumentException
{
    public static function withId(string $basketId): self
    {
        return new self(
            sprintf('Basket with ID "%s" not found', $basketId)
        );
    }

    public static function notBoundWithInpost(string $basketId): self
    {
        return new self(
            sprintf('Basket with ID "%s" is not bound with InPost Pay', $basketId)
        );
    }
}
