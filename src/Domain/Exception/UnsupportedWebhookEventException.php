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

final class UnsupportedWebhookEventException extends DomainException
{
    public static function forType(string $eventType): self
    {
        return new self(
            sprintf('No handler found for webhook event type: %s', $eventType)
        );
    }
}
