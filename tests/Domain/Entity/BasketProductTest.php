<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Domain\Entity;

use Crehler\InpostPay\Domain\Entity\{BasketProduct, ProductDelivery, ProductQuantity};
use Crehler\InpostPay\Domain\ValueObject\{DeliveryType, Money, ProductType, QuantityType};
use PHPUnit\Framework\TestCase;

class BasketProductTest extends TestCase
{
    public function testPhysicalProductSerializesDigitalDeliveryExclusion(): void
    {
        $product = $this->createProduct(
            productType: ProductType::PRODUCT,
            deliveryProduct: [
                new ProductDelivery(DeliveryType::DIGITAL, false),
            ],
        );

        $data = $product->toArray();

        static::assertSame('PRODUCT', $data['product_type']);
        static::assertSame(
            [
                ['delivery_type' => 'DIGITAL', 'if_delivery_available' => false],
            ],
            $data['delivery_product'],
        );
    }

    public function testDigitalProductSerializesTypeWithoutDeliveryProduct(): void
    {
        $product = $this->createProduct(productType: ProductType::DIGITAL, deliveryProduct: []);

        $data = $product->toArray();

        static::assertSame('DIGITAL', $data['product_type']);
        static::assertArrayNotHasKey('delivery_product', $data);
    }

    public function testToArrayOmitsEmptyDeliveryProductAndNullProductType(): void
    {
        $product = $this->createProduct(productType: null, deliveryProduct: []);

        $data = $product->toArray();

        static::assertArrayNotHasKey('product_type', $data);
        static::assertArrayNotHasKey('delivery_product', $data);
    }

    public function testDigitalDetectionHelpers(): void
    {
        $digital = $this->createProduct(productType: ProductType::DIGITAL, deliveryProduct: []);
        $physical = $this->createProduct(productType: ProductType::PRODUCT, deliveryProduct: []);

        static::assertTrue($digital->isDigital());
        static::assertFalse($digital->isPhysical());
        static::assertFalse($physical->isDigital());
        static::assertTrue($physical->isPhysical());
    }

    private function createProduct(?ProductType $productType, array $deliveryProduct): BasketProduct
    {
        return new BasketProduct(
            productId: 'line-item-id',
            productName: 'Test Product',
            basePrice: Money::fromShopwarePrice(net: 81.30, gross: 100.00),
            quantity: new ProductQuantity(
                quantity: 1,
                quantityType: QuantityType::INTEGER,
                quantityUnit: ProductQuantity::PIECE,
            ),
            productType: $productType,
            deliveryProduct: $deliveryProduct,
        );
    }
}
