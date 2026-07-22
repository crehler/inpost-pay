<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Application\Service;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Application\Service\OrderDataExtractor;
use Crehler\InpostPay\Infrastructure\Logger\ExceptionLogger;
use Crehler\InpostPay\Infrastructure\Persistence\Repository\ShopwareOrderRepository;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use Crehler\InpostPay\Infrastructure\Service\ProductUrlGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;

final class OrderDataExtractorTest extends TestCase
{
    private OrderDataExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new OrderDataExtractor(
            $this->createMock(ShopwareOrderRepository::class),
            $this->createMock(InpostPayConfigProvider::class),
            $this->createMock(InpostBasketSessionService::class),
            $this->createMock(CashRounding::class),
            $this->createMock(ProductUrlGenerator::class),
            $this->createMock(ExceptionLogger::class),
        );
    }

    public function testSumOrderDeliveryCosts_emptyCollection_returnsZero(): void
    {
        $deliveries = new OrderDeliveryCollection();

        $result = $this->invokeSumOrderDeliveryCosts($deliveries);

        self::assertEqualsWithDelta(0.0, $result['net'], 0.001);
        self::assertEqualsWithDelta(0.0, $result['gross'], 0.001);
    }

    public function testSumOrderDeliveryCosts_singleDelivery_returnsThatDelivery(): void
    {
        $deliveries = new OrderDeliveryCollection([
            $this->makeDelivery('delivery-1', 29.0, 5.42),
        ]);

        $result = $this->invokeSumOrderDeliveryCosts($deliveries);

        self::assertEqualsWithDelta(29.0, $result['gross'], 0.001);
        self::assertEqualsWithDelta(29.0 - 5.42, $result['net'], 0.001);
    }

    public function testSumOrderDeliveryCosts_multipleDeliveries_sumsAll(): void
    {
        $deliveries = new OrderDeliveryCollection([
            $this->makeDelivery('delivery-1', 29.0, 5.42),
            $this->makeDelivery('delivery-2', 29.0, 5.42),
        ]);

        $result = $this->invokeSumOrderDeliveryCosts($deliveries);

        self::assertEqualsWithDelta(58.0, $result['gross'], 0.001);
        self::assertEqualsWithDelta(58.0 - 10.84, $result['net'], 0.001);
    }

    public function testSumOrderDeliveryCosts_skipsNegativeDeliveries(): void
    {
        $deliveries = new OrderDeliveryCollection([
            $this->makeDelivery('delivery-1', 29.0, 5.42),
            $this->makeDelivery('delivery-2', 29.0, 5.42),
            $this->makeDelivery('delivery-promo', -10.0, -1.87),
        ]);

        $result = $this->invokeSumOrderDeliveryCosts($deliveries);

        self::assertEqualsWithDelta(58.0, $result['gross'], 0.001);
        self::assertEqualsWithDelta(58.0 - 10.84, $result['net'], 0.001);
    }

    private function invokeSumOrderDeliveryCosts(OrderDeliveryCollection $deliveries): array
    {
        $reflection = new ReflectionClass($this->extractor);
        $method = $reflection->getMethod('sumOrderDeliveryCosts');
        $method->setAccessible(true);

        return $method->invoke($this->extractor, $deliveries);
    }

    private function makeDelivery(string $id, float $gross, float $tax): OrderDeliveryEntity
    {
        $taxCollection = new CalculatedTaxCollection();
        if ($tax !== 0.0) {
            $taxCollection->add(new CalculatedTax($tax, 23.0, $gross));
        }

        $price = new CalculatedPrice(
            unitPrice: $gross,
            totalPrice: $gross,
            calculatedTaxes: $taxCollection,
            taxRules: new TaxRuleCollection(),
        );

        $delivery = new OrderDeliveryEntity();
        $delivery->setUniqueIdentifier($id);
        $delivery->setShippingCosts($price);

        return $delivery;
    }
}
