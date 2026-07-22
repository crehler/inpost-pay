<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum DeliveryType: string
{
    case APM = 'APM';
    case COURIER = 'COURIER';
    case DIGITAL = 'DIGITAL';

    public function isPhysical(): bool
    {
        return $this === self::APM || $this === self::COURIER;
    }

    public function isDigital(): bool
    {
        return $this === self::DIGITAL;
    }

    /**
     * Get available optional service codes for this delivery type.
     *
     * @return ServiceCode[]
     */
    public function getAvailableServiceCodes(): array
    {
        return ServiceCode::getAvailableForDeliveryType($this);
    }
}
