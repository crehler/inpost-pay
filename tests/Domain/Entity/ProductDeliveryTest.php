<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Domain\Entity;

use Crehler\InpostPay\Domain\Entity\ProductDelivery;
use Crehler\InpostPay\Domain\ValueObject\DeliveryType;
use PHPUnit\Framework\TestCase;

class ProductDeliveryTest extends TestCase
{
    public function testToArrayWithAvailableDelivery(): void
    {
        $delivery = new ProductDelivery(
            deliveryType: DeliveryType::DIGITAL,
            ifDeliveryAvailable: true,
        );

        static::assertSame(
            [
                'delivery_type' => 'DIGITAL',
                'if_delivery_available' => true,
            ],
            $delivery->toArray(),
        );
    }

    public function testToArrayWithUnavailableDelivery(): void
    {
        $delivery = new ProductDelivery(
            deliveryType: DeliveryType::APM,
            ifDeliveryAvailable: false,
        );

        static::assertSame(
            [
                'delivery_type' => 'APM',
                'if_delivery_available' => false,
            ],
            $delivery->toArray(),
        );
    }

    public function testDeliveryIsAvailableByDefault(): void
    {
        $delivery = new ProductDelivery(deliveryType: DeliveryType::COURIER);

        static::assertTrue($delivery->ifDeliveryAvailable);
    }
}
