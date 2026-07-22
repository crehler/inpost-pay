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

final class InvalidWebhookSignatureException extends DomainException
{
    public static function mismatch(): self
    {
        return new self('Webhook signature validation failed');
    }

    public static function missingSecret(): self
    {
        return new self('Merchant secret not configured');
    }
}
