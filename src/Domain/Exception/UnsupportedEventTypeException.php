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

class UnsupportedEventTypeException extends DomainException
{
    public static function unknown(string $eventType): self
    {
        return new self(
            sprintf('Event type "%s" is not supported', $eventType)
        );
    }

    public static function missingData(string $eventType): self
    {
        return new self(
            sprintf('Event type "%s" requires event data that was not provided', $eventType)
        );
    }
}
