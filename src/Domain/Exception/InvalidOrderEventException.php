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

final class InvalidOrderEventException extends DomainException
{
    public static function invalidPaymentStatus(string $status): self
    {
        return new self(
            sprintf('Invalid payment status: %s', $status)
        );
    }

    public static function missingEventData(string $field): self
    {
        return new self(
            sprintf('Missing required event data field: %s', $field)
        );
    }

    public static function noTransaction(): self
    {
        return new self('Order has no payment transactions');
    }
}
