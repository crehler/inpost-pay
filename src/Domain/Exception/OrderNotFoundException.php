<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use DomainException;

use function sprintf;

class OrderNotFoundException extends DomainException
{
    public static function withId(string $orderId): self
    {
        return new self(sprintf('Order with ID "%s" not found', $orderId));
    }
}
