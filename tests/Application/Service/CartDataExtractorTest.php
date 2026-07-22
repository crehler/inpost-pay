<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Application\Service;

use Crehler\InpostPay\Application\Service\CartDataExtractor;
use Crehler\InpostPay\Application\Service\ServiceNameTranslator;
use Crehler\InpostPay\Domain\ValueObject\Money;
use Crehler\InpostPay\Application\Service\BasePricingContextFactory;
use Crehler\InpostPay\Infrastructure\Provider\DeliveryMappingProvider;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use Crehler\InpostPay\Infrastructure\Provider\ServiceOptionsProvider;
use Crehler\InpostPay\Infrastructure\Service\ProductUrlGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryProcessor;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class CartDataExtractorTest extends TestCase
{
    public function testSumDeliveryCosts_emptyOrNullCollection_returnsZero(): void
    {
        $extractor = $this->createExtractor();

        $nullResult = $this->invokeSumDeliveryCosts($extractor, null);
        self::assertSame(0.0, $nullResult['net']);
        self::assertSame(0.0, $nullResult['gross']);

        $emptyResult = $this->invokeSumDeliveryCosts($extractor, new DeliveryCollection());
        self::assertSame(0.0, $emptyResult['net']);
        self::assertSame(0.0, $emptyResult['gross']);
    }

    public function testSumDeliveryCosts_singleDelivery_returnsThatDelivery(): void
    {
        $extractor = $this->createExtractor();

        // 29 zl gross with 23% VAT -> tax amount = 5.42 (computed from 29 * 0.23 / 1.23)
        $taxAmount = round(29.0 * 0.23 / 1.23, 2); // 5.42
        $deliveries = new DeliveryCollection([
            $this->mockDelivery(grossTotal: 29.0, taxAmount: $taxAmount),
        ]);

        $result = $this->invokeSumDeliveryCosts($extractor, $deliveries);

        self::assertSame(29.0, $result['gross']);
        self::assertSame(29.0 - $taxAmount, $result['net']);
    }

    public function testSumDeliveryCosts_multipleDeliveries_sumsAll(): void
    {
        $extractor = $this->createExtractor();

        $taxAmount = round(29.0 * 0.23 / 1.23, 2); // 5.42
        $deliveries = new DeliveryCollection([
            $this->mockDelivery(grossTotal: 29.0, taxAmount: $taxAmount),
            $this->mockDelivery(grossTotal: 29.0, taxAmount: $taxAmount),
        ]);

        $result = $this->invokeSumDeliveryCosts($extractor, $deliveries);

        self::assertSame(58.0, $result['gross']);
        self::assertSame((29.0 - $taxAmount) * 2, $result['net']);
    }

    public function testSumDeliveryCosts_skipsNegativeDeliveries(): void
    {
        $extractor = $this->createExtractor();

        $taxAmount = round(29.0 * 0.23 / 1.23, 2);
        $deliveries = new DeliveryCollection([
            $this->mockDelivery(grossTotal: 29.0, taxAmount: $taxAmount),
            $this->mockDelivery(grossTotal: 29.0, taxAmount: $taxAmount),
            // Promotional negative delivery — must be skipped.
            $this->mockDelivery(grossTotal: -10.0, taxAmount: -1.87),
        ]);

        $result = $this->invokeSumDeliveryCosts($extractor, $deliveries);

        self::assertSame(58.0, $result['gross']);
        self::assertSame((29.0 - $taxAmount) * 2, $result['net']);
    }

    public function testSumDeliveryCosts_zeroDelivery_returnsZero(): void
    {
        $extractor = $this->createExtractor();

        $deliveries = new DeliveryCollection([
            $this->mockDelivery(grossTotal: 0.0, taxAmount: 0.0),
        ]);

        $result = $this->invokeSumDeliveryCosts($extractor, $deliveries);

        self::assertSame(0.0, $result['gross']);
        self::assertSame(0.0, $result['net']);
    }

    public function testCollectCartDeliveriesByShippingMethodIds_emptyIdsReturnsEmpty(): void
    {
        $extractor = $this->createExtractorWithoutConstructor();
        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection());

        $result = $this->invokeCollectCartDeliveries($extractor, $cart, []);

        self::assertSame(0, $result->count());
    }

    public function testCollectCartDeliveriesByShippingMethodIds_filtersByMethodId(): void
    {
        $extractor = $this->createExtractorWithoutConstructor();

        $matchingDelivery = $this->mockDeliveryWithShippingMethod(grossTotal: 59.99, taxAmount: 11.21, methodId: 'method-A');
        $otherDelivery = $this->mockDeliveryWithShippingMethod(grossTotal: 19.99, taxAmount: 3.74, methodId: 'method-B');

        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$matchingDelivery, $otherDelivery]));

        $result = $this->invokeCollectCartDeliveries($extractor, $cart, ['method-A']);

        self::assertSame(1, $result->count());
        self::assertSame($matchingDelivery, $result->first());
    }

    public function testCollectCartDeliveriesByShippingMethodIds_collectsPartialDeliverySplit(): void
    {
        $extractor = $this->createExtractorWithoutConstructor();

        $primary = $this->mockDeliveryWithShippingMethod(grossTotal: 59.99, taxAmount: 11.21, methodId: 'courier');
        $secondary = $this->mockDeliveryWithShippingMethod(grossTotal: 29.99, taxAmount: 5.61, methodId: 'courier');

        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$primary, $secondary]));

        $result = $this->invokeCollectCartDeliveries($extractor, $cart, ['courier']);

        self::assertSame(2, $result->count());

        $sum = $this->invokeSumDeliveryCosts($extractor, $result);
        self::assertEqualsWithDelta(89.98, $sum['gross'], 0.001);
    }

    public function testCollectCartDeliveriesByShippingMethodIds_skipsNegativePromoDelivery(): void
    {
        $extractor = $this->createExtractorWithoutConstructor();

        $real = $this->mockDeliveryWithShippingMethod(grossTotal: 59.99, taxAmount: 11.21, methodId: 'courier');
        $promo = $this->mockDeliveryWithShippingMethod(grossTotal: -10.00, taxAmount: -1.87, methodId: 'courier');

        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$real, $promo]));

        $result = $this->invokeCollectCartDeliveries($extractor, $cart, ['courier']);

        self::assertSame(1, $result->count());
        self::assertSame($real, $result->first());
    }

    public function testCollectCartDeliveriesByShippingMethodIds_acceptsMultipleMappedIds(): void
    {
        $extractor = $this->createExtractorWithoutConstructor();

        $a = $this->mockDeliveryWithShippingMethod(grossTotal: 10.00, taxAmount: 1.87, methodId: 'method-A');
        $b = $this->mockDeliveryWithShippingMethod(grossTotal: 12.00, taxAmount: 2.24, methodId: 'method-B');
        $c = $this->mockDeliveryWithShippingMethod(grossTotal: 5.00, taxAmount: 0.93, methodId: 'method-C');

        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$a, $b, $c]));

        $result = $this->invokeCollectCartDeliveries($extractor, $cart, ['method-A', 'method-B']);

        self::assertSame(2, $result->count());
    }

    public function testResolveBaseDeliveryPrice_ignoresStorefrontPaidMethod_buildsBaseMethod(): void
    {
        $baseMethodId = 'base-courier';

        // Cart currently uses a DIFFERENT, paid storefront method (e.g. cash-on-delivery
        // shipping at 25 zl). Its surcharge must NOT become the InPost base price; the
        // base method is built fresh instead.
        $storefrontDelivery = $this->mockDeliveryWithShippingMethod(grossTotal: 25.0, taxAmount: 4.67, methodId: 'cod-paid');
        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$storefrontDelivery]));
        $cart->method('getLineItems')->willReturn(new LineItemCollection());

        $baseShippingMethod = $this->createMock(ShippingMethodEntity::class);
        $baseShippingMethod->method('getId')->willReturn($baseMethodId);

        $builtBaseDelivery = $this->mockDelivery(grossTotal: 15.0, taxAmount: round(15.0 * 0.23 / 1.23, 2));
        $deliveryBuilder = $this->createMock(DeliveryBuilder::class);
        $deliveryBuilder->method('buildByUsingShippingMethod')->willReturn(new DeliveryCollection([$builtBaseDelivery]));

        $extractor = $this->createExtractorWith(deliveryBuilder: $deliveryBuilder);

        $price = $this->invokeResolveBaseDeliveryPrice($extractor, $cart, $baseMethodId, [$baseMethodId => $baseShippingMethod]);

        self::assertNotNull($price);
        self::assertSame(15.0, $price->getGross());
    }

    public function testResolveBaseDeliveryPrice_sumsPartialSplitOnBaseMethod(): void
    {
        $baseMethodId = 'base-courier';

        $first = $this->mockDeliveryWithShippingMethod(grossTotal: 10.0, taxAmount: 1.87, methodId: $baseMethodId);
        $second = $this->mockDeliveryWithShippingMethod(grossTotal: 6.0, taxAmount: 1.12, methodId: $baseMethodId);

        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$first, $second]));

        // Base method is available; cart already uses it, so the cart-delivery sum path
        // is taken (no build, no constructor services touched).
        $baseShippingMethod = $this->createMock(ShippingMethodEntity::class);
        $baseShippingMethod->method('getId')->willReturn($baseMethodId);

        $extractor = $this->createExtractorWithoutConstructor();

        $price = $this->invokeResolveBaseDeliveryPrice($extractor, $cart, $baseMethodId, [$baseMethodId => $baseShippingMethod]);

        self::assertNotNull($price);
        self::assertEqualsWithDelta(16.0, $price->getGross(), 0.001);
    }

    public function testResolveBaseDeliveryPrice_methodUnavailable_returnsNull(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection());

        $extractor = $this->createExtractorWith();

        $price = $this->invokeResolveBaseDeliveryPrice($extractor, $cart, 'missing-method', []);

        self::assertNull($price);
    }

    public function testResolveBaseDeliveryPrice_unavailableBaseMethod_ignoresStaleCartDelivery_returnsNull(): void
    {
        $baseMethodId = 'base-courier';

        // A stale cart delivery still references the base method, but the method is no
        // longer available (absent from shippingMethodsById). It must NOT revive it.
        $stale = $this->mockDeliveryWithShippingMethod(
            grossTotal: 15.0,
            taxAmount: round(15.0 * 0.23 / 1.23, 2),
            methodId: $baseMethodId
        );

        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$stale]));

        $extractor = $this->createExtractorWithoutConstructor();

        $price = $this->invokeResolveBaseDeliveryPrice($extractor, $cart, $baseMethodId, []);

        self::assertNull($price);
    }

    public function testResolveBaseDeliveryPrice_neutralisesCodSurcharge_repricesCartDeliveryUnderBaseContext(): void
    {
        $baseMethodId = 'base-courier';

        // Cart uses the base method at the COD-surcharged tier (73 = 58 + 15); real
        // Delivery so the clone + cost reset takes effect.
        $codPricedDelivery = $this->realDeliveryOnMethod($baseMethodId, grossTotal: 73.0);
        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveries')->willReturn(new DeliveryCollection([$codPricedDelivery]));

        $baseShippingMethod = $this->createMock(ShippingMethodEntity::class);
        $baseShippingMethod->method('getId')->willReturn($baseMethodId);

        // Under the non-COD pricing context the calculator re-matches the base tier (58 zl).
        $deliveryCalculator = $this->createMock(DeliveryCalculator::class);
        $deliveryCalculator->method('calculate')->willReturnCallback(
            function ($data, $cart, DeliveryCollection $deliveries): void {
                foreach ($deliveries as $delivery) {
                    $delivery->setShippingCosts(new CalculatedPrice(
                        58.0,
                        58.0,
                        new CalculatedTaxCollection([new CalculatedTax(10.85, 23.0, 58.0)]),
                        new TaxRuleCollection(),
                    ));
                }
            }
        );

        // Build without the constructor and inject only the calculator, so the final
        // InpostPayConfigProvider is never doubled (PHPUnit 11 forbids it).
        $extractor = $this->createExtractorWithoutConstructor();
        $calculatorProperty = (new ReflectionClass($extractor))->getProperty('deliveryCalculator');
        $calculatorProperty->setAccessible(true);
        $calculatorProperty->setValue($extractor, $deliveryCalculator);

        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('resolveBaseDeliveryPrice');
        $method->setAccessible(true);

        // Distinct context instances => pricing context differs from the original, which
        // flags the COD-neutralisation path without invoking buildBasePricingContext.
        /** @var Money|null $price */
        $price = $method->invoke(
            $extractor,
            $cart,
            $baseMethodId,
            [$baseMethodId => $baseShippingMethod],
            $this->createMock(SalesChannelContext::class),
            $this->createMock(SalesChannelContext::class),
        );

        self::assertNotNull($price);
        // Re-priced base tier (58), NOT the live COD-surcharged cart cost (73).
        self::assertSame(58.0, $price->getGross());
        // The original cart delivery must stay untouched (clone was repriced, not it).
        self::assertSame(73.0, $codPricedDelivery->getShippingCosts()->getTotalPrice());
    }

    public function testCloneDeliveriesWithResetCosts_zeroesClonesAndKeepsOriginals(): void
    {
        $extractor = $this->createExtractorWithoutConstructor();

        $original = $this->realDeliveryOnMethod('base-courier', grossTotal: 73.0);
        $source = new DeliveryCollection([$original]);

        $method = (new ReflectionClass($extractor))->getMethod('cloneDeliveriesWithResetCosts');
        $method->setAccessible(true);

        /** @var DeliveryCollection $clones */
        $clones = $method->invoke($extractor, $source);

        self::assertSame(1, $clones->count());
        // Clone cost reset to zero so DeliveryCalculator re-matches the price tier.
        self::assertSame(0.0, $clones->first()->getShippingCosts()->getTotalPrice());
        // Original cart delivery untouched - repricing must not mutate the live cart.
        self::assertSame(73.0, $original->getShippingCosts()->getTotalPrice());
        self::assertNotSame($original, $clones->first());
    }

    /**
     * Use this when the test exercises a private method that does not touch
     * any of the constructor-injected services. Avoids having to mock final
     * provider classes (InpostPayConfigProvider, DeliveryMappingProvider).
     */
    private function createExtractorWithoutConstructor(): CartDataExtractor
    {
        /** @var CartDataExtractor $extractor */
        $extractor = (new ReflectionClass(CartDataExtractor::class))->newInstanceWithoutConstructor();

        return $extractor;
    }

    private function createExtractor(): CartDataExtractor
    {
        return new CartDataExtractor(
            $this->createMock(InpostPayConfigProvider::class),
            $this->createMock(CashRounding::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(DeliveryMappingProvider::class),
            $this->createMock(AbstractShippingMethodRoute::class),
            $this->createMock(DeliveryBuilder::class),
            $this->createMock(DeliveryCalculator::class),
            $this->createMock(PromotionDeliveryProcessor::class),
            $this->createMock(ServiceOptionsProvider::class),
            $this->createMock(ServiceNameTranslator::class),
            $this->createMock(ProductUrlGenerator::class),
            $this->createMock(BasePricingContextFactory::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    private function createExtractorWith(
        ?DeliveryBuilder $deliveryBuilder = null,
        ?DeliveryCalculator $deliveryCalculator = null,
    ): CartDataExtractor {
        return new CartDataExtractor(
            $this->createMock(InpostPayConfigProvider::class),
            $this->createMock(CashRounding::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(DeliveryMappingProvider::class),
            $this->createMock(AbstractShippingMethodRoute::class),
            $deliveryBuilder ?? $this->createMock(DeliveryBuilder::class),
            $deliveryCalculator ?? $this->createMock(DeliveryCalculator::class),
            $this->createMock(PromotionDeliveryProcessor::class),
            $this->createMock(ServiceOptionsProvider::class),
            $this->createMock(ServiceNameTranslator::class),
            $this->createMock(ProductUrlGenerator::class),
            $this->createMock(BasePricingContextFactory::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * @param array<string, ShippingMethodEntity> $shippingMethodsById
     */
    private function invokeResolveBaseDeliveryPrice(
        CartDataExtractor $extractor,
        Cart $cart,
        string $baseMethodId,
        array $shippingMethodsById,
    ): ?Money {
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('resolveBaseDeliveryPrice');
        $method->setAccessible(true);

        // Pass the same context as both the original and the pricing context, so the
        // existing assertions cover the non-neutralised path (no COD surcharge swap).
        $context = $this->createMock(SalesChannelContext::class);

        /** @var Money|null $result */
        $result = $method->invoke($extractor, $cart, $baseMethodId, $shippingMethodsById, $context, $context);

        return $result;
    }

    /**
     * @return array{net: float, gross: float}
     */
    private function invokeSumDeliveryCosts(CartDataExtractor $extractor, ?DeliveryCollection $deliveries): array
    {
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('sumDeliveryCosts');
        $method->setAccessible(true);

        /** @var array{net: float, gross: float} $result */
        $result = $method->invoke($extractor, $deliveries);

        return $result;
    }

    private function mockDelivery(float $grossTotal, float $taxAmount): Delivery
    {
        $price = new CalculatedPrice(
            unitPrice: $grossTotal,
            totalPrice: $grossTotal,
            calculatedTaxes: new CalculatedTaxCollection([
                new CalculatedTax($taxAmount, 23.0, $grossTotal),
            ]),
            taxRules: new TaxRuleCollection(),
        );

        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getShippingCosts')->willReturn($price);

        return $delivery;
    }

    private function mockDeliveryWithShippingMethod(float $grossTotal, float $taxAmount, string $methodId): Delivery
    {
        $price = new CalculatedPrice(
            unitPrice: $grossTotal,
            totalPrice: $grossTotal,
            calculatedTaxes: new CalculatedTaxCollection([
                new CalculatedTax($taxAmount, 23.0, $grossTotal),
            ]),
            taxRules: new TaxRuleCollection(),
        );

        $shippingMethod = $this->createMock(ShippingMethodEntity::class);
        $shippingMethod->method('getId')->willReturn($methodId);

        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getShippingCosts')->willReturn($price);
        $delivery->method('getShippingMethod')->willReturn($shippingMethod);

        return $delivery;
    }

    private function realDeliveryOnMethod(string $methodId, float $grossTotal): Delivery
    {
        $shippingMethod = $this->createMock(ShippingMethodEntity::class);
        $shippingMethod->method('getId')->willReturn($methodId);

        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new DateTimeImmutable(), new DateTimeImmutable()),
            $shippingMethod,
            new ShippingLocation($this->createMock(CountryEntity::class), null, null),
            new CalculatedPrice(
                $grossTotal,
                $grossTotal,
                new CalculatedTaxCollection([new CalculatedTax(round($grossTotal * 0.23 / 1.23, 2), 23.0, $grossTotal)]),
                new TaxRuleCollection(),
            ),
        );
    }

    /**
     * @param string[] $shippingMethodIds
     */
    private function invokeCollectCartDeliveries(CartDataExtractor $extractor, Cart $cart, array $shippingMethodIds): DeliveryCollection
    {
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('collectCartDeliveriesByShippingMethodIds');
        $method->setAccessible(true);

        /** @var DeliveryCollection $result */
        $result = $method->invoke($extractor, $cart, $shippingMethodIds);

        return $result;
    }
}
