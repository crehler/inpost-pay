<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum ServiceCode: string
{
    case COD = 'COD';
    case PWW = 'PWW';

    public function isAvailabilityTimeDependent(): bool
    {
        return $this === self::PWW;
    }

    public function getDisplayNameKey(): string
    {
        return match ($this) {
            self::COD => 'inpostPay.service.cashOnDelivery',
            self::PWW => 'inpostPay.service.weekendDelivery',
        };
    }

    /**
     * @return ServiceCode[]
     */
    public static function getAvailableForDeliveryType(DeliveryType $type): array
    {
        return match ($type) {
            DeliveryType::APM => [self::COD, self::PWW],
            DeliveryType::COURIER => [self::COD],
            DeliveryType::DIGITAL => [],
        };
    }
}
