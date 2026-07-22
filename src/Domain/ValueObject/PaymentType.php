<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use InvalidArgumentException;

enum PaymentType: string
{
    case CARD = 'CARD';
    case CARD_TOKEN = 'CARD_TOKEN';
    case GOOGLE_PAY = 'GOOGLE_PAY';
    case APPLE_PAY = 'APPLE_PAY';
    case BLIK_CODE = 'BLIK_CODE';
    case BLIK_TOKEN = 'BLIK_TOKEN';
    case PAY_BY_LINK = 'PAY_BY_LINK';
    case SHOPPING_LIMIT = 'SHOPPING_LIMIT';
    case DEFERRED_PAYMENT = 'DEFERRED_PAYMENT';
    case CASH_ON_DELIVERY = 'CASH_ON_DELIVERY';
    case FREE_ORDER = 'FREE_ORDER';

    public function isCard(): bool
    {
        return $this === self::CARD || $this === self::CARD_TOKEN;
    }

    public function isBlik(): bool
    {
        return $this === self::BLIK_CODE || $this === self::BLIK_TOKEN;
    }

    public function isWallet(): bool
    {
        return $this === self::GOOGLE_PAY || $this === self::APPLE_PAY;
    }

    public function requiresDelivery(): bool
    {
        return $this === self::CASH_ON_DELIVERY;
    }

    public static function getEnum(string $paymentTypeName): self
    {
        return match ($paymentTypeName) {
            'CARD' => self::CARD,
            'CARD_TOKEN' => self::CARD_TOKEN,
            'GOOGLE_PAY' => self::GOOGLE_PAY,
            'APPLE_PAY' => self::APPLE_PAY,
            'BLIK_CODE' => self::BLIK_CODE,
            'BLIK_TOKEN' => self::BLIK_TOKEN,
            'PAY_BY_LINK' => self::PAY_BY_LINK,
            'SHOPPING_LIMIT' => self::SHOPPING_LIMIT,
            'DEFERRED_PAYMENT' => self::DEFERRED_PAYMENT,
            'CASH_ON_DELIVERY' => self::CASH_ON_DELIVERY,
            'FREE_ORDER' => self::FREE_ORDER,
            default => throw new InvalidArgumentException('Invalid payment type: ' . $paymentTypeName),
        };
    }
}
