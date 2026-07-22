<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum PaymentStatus: string
{
    case AUTHORIZED = 'AUTHORIZED';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case PENDING = 'PENDING';
    case REFUNDED = 'REFUNDED';
    case UNPAID = 'UNPAID';
    case STARTED = 'STARTED';
    case DECLINED = 'DECLINED';
    case ERROR = 'ERROR';
    case COD = 'COD';

    public function isSuccessful(): bool
    {
        return match ($this) {
            self::AUTHORIZED, self::COMPLETED => true,
            default => false,
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED, self::REFUNDED, self::DECLINED, self::ERROR => true,
            self::AUTHORIZED, self::PENDING, self::UNPAID, self::STARTED, self::COD => false,
        };
    }

    public function requiresStateChange(): bool
    {
        return match ($this) {
            self::UNPAID, self::STARTED => false,
            default => true,
        };
    }

    public function requiresPaymentOnDelivery(): bool
    {
        return $this === self::COD;
    }
}
