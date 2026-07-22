<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\DeliveryType;

readonly class ProductDelivery
{
    public function __construct(
        public DeliveryType $deliveryType,
        public bool $ifDeliveryAvailable = true,
    ) {
    }

    public function toArray(): array
    {
        return [
            'delivery_type' => $this->deliveryType->value,
            'if_delivery_available' => $this->ifDeliveryAvailable,
        ];
    }
}
