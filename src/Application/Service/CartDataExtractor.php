<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\Entity\{BasketNotice, BasketProduct, BasketSummary, DeliveryAdditionalOption, DeliveryOption, OrderLine, ProductAttribute, ProductImage, ProductQuantity, PromoCode};
use Crehler\InpostPay\Domain\ValueObject\{BasketPricing, DeliveryType, Money, PaymentType, ProductType, Quantity, QuantityType};
use Crehler\InpostPay\Domain\ValueObject\Order\OrderPricing;
use Crehler\InpostPay\Infrastructure\Provider\{DeliveryMappingProvider, InpostPayConfigProvider, ServiceOptionsProvider};
use Crehler\InpostPay\Infrastructure\Service\ProductUrlGenerator;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\{Cart, CartBehavior};
use Shopware\Core\Checkout\Cart\Delivery\{DeliveryBuilder, DeliveryCalculator, DeliveryProcessor};
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\{CartDataCollection, LineItem, LineItemCollection};
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\{AbsolutePriceDefinition, CalculatedPrice, PercentagePriceDefinition};
use Shopware\Core\Checkout\Cart\Tax\Struct\{CalculatedTaxCollection, TaxRuleCollection};
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\{PromotionDeliveryProcessor, PromotionProcessor};
use Shopware\Core\Checkout\Shipping\SalesChannel\{AbstractShippingMethodRoute, ShippingMethodRoute};
use Shopware\Core\Content\Product\{ProductEntity, State};
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function abs;
use function array_filter;
use function array_flip;
use function array_map;
use function array_values;
use function count;
use function explode;
use function filter_var;
use function implode;
use function is_array;
use function is_string;
use function max;
use function min;
use function parse_url;
use function round;

readonly class CartDataExtractor
{
    /**
     * Generic opt-in cart extension key. When present on the cart, no InPost Pay
     * delivery options are offered, so the InPost Pay app shows the "buy via the
     * shop" message. Any integrator (e.g. a plugin handling shop-specific delivery
     * constraints) may set this extension; InPost Pay itself stays unaware of why.
     */
    public const SUPPRESS_DELIVERY_OPTIONS_EXTENSION = 'inpostPaySuppressDeliveryOptions';

    public function __construct(
        private InpostPayConfigProvider $configProvider,
        private CashRounding $cashRounding,
        private EntityRepository $productRepository,
        private DeliveryMappingProvider $deliveryMappingProvider,
        #[Autowire(service: ShippingMethodRoute::class)]
        private AbstractShippingMethodRoute $shippingMethodRoute,
        private DeliveryBuilder $deliveryBuilder,
        private DeliveryCalculator $deliveryCalculator,
        private PromotionDeliveryProcessor $promotionDeliveryProcessor,
        private ServiceOptionsProvider $serviceOptionsProvider,
        private ServiceNameTranslator $serviceNameTranslator,
        private ProductUrlGenerator $productUrlGenerator,
        private BasePricingContextFactory $basePricingContextFactory,
        private LoggerInterface $logger,
        #[Autowire('%shopware.cart.expire_days%')]
        private int $cartExpireDays,
    ) {
    }

    /**
     * @param array<string, string>|null $additionalParameters Associative array of key-value pairs
     */
    public function extractBasketSummary(
        Cart $cart,
        SalesChannelContext $context,
        ?string $errorMessage = null,
        ?array $additionalParameters = null,
    ): BasketSummary {
        $currency = $context->getCurrency()->getIsoCode();
        $paymentTypes = $this->getPaymentTypes($context);
        $expirationDate = new DateTimeImmutable("+{$this->cartExpireDays} days");

        $basketNotice = null;
        if ($errorMessage !== null) {
            $basketNotice = BasketNotice::error($errorMessage);
        }

        // Calculate all three price types
        $prices = $this->calculateBasketPrices($cart, $context);

        return new BasketSummary(
            basketBasePrice: $prices->base,
            currency: $currency,
            paymentTypes: $paymentTypes,
            basketFinalPrice: $prices->final,
            basketPromoPrice: $prices->promo,
            freeBasket: $prices->final->isZero(),
            basketExpirationDate: $expirationDate,
            basketAdditionalInformation: null,
            basketNotice: $basketNotice,
            additionalParameters: $additionalParameters,
        );
    }

    /**
     * @param BasketProduct[] $products
     */
    public function extractDeliveryOptions(Cart $cart, SalesChannelContext $context, array $products): array
    {
        // Integrator signalled that no delivery should be offered for this cart.
        if ($cart->hasExtension(self::SUPPRESS_DELIVERY_OPTIONS_EXTENSION)) {
            return [];
        }

        $request = new Request(['onlyAvailable' => true]);

        $criteria = new Criteria();
        $criteria->addAssociation('prices');
        $criteria->addAssociation('deliveryTime');
        $criteria->addAssociation('tax');

        $shippingMethods = $this->shippingMethodRoute
            ->load($request, $context, $criteria)
            ->getShippingMethods();

        $shippingMethodsById = [];
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethodsById[$shippingMethod->getId()] = $shippingMethod;
        }

        $salesChannelId = $context->getSalesChannel()->getId();
        $deliveryOptions = [];
        $cartHasDigitalProduct = $this->containsDigitalProduct($products);

        // Price under a non-COD context so the COD surcharge is not double-counted - SUEZ-1045.
        $pricingContext = $this->basePricingContextFactory->create($cart, $context);

        foreach (DeliveryType::cases() as $deliveryType) {
            if ($deliveryType === DeliveryType::DIGITAL && !$cartHasDigitalProduct) {
                continue;
            }

            // First configured method that is actually available for this cart - SUEZ-1045.
            $deliveryPrice = null;
            foreach ($this->deliveryMappingProvider->getMethodIdsByType($deliveryType, $salesChannelId) as $baseMethodId) {
                $deliveryPrice = $this->resolveBaseDeliveryPrice($cart, $baseMethodId, $shippingMethodsById, $context, $pricingContext);
                if ($deliveryPrice !== null) {
                    break;
                }
            }

            if ($deliveryPrice === null) {
                continue;
            }

            $deliveryOptions[] = new DeliveryOption(
                deliveryType: $deliveryType,
                deliveryDate: new DateTimeImmutable('+3 days'),
                deliveryPrice: $deliveryPrice,
                additionalOptions: $this->buildAdditionalOptions($deliveryType, $context),
                freeDeliveryMinimumGrossPrice: null,
            );
        }

        return $deliveryOptions;
    }

    /**
     * @return PromoCode[]
     */
    public function extractPromoCodes(Cart $cart): array
    {
        $promoLineItems = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $promoCodes = [];

        foreach ($promoLineItems as $lineItem) {
            $code = $lineItem->getReferencedId();
            if ($code === null || $code === '') {
                continue;
            }

            $promoCodes[] = new PromoCode(
                name: $lineItem->getLabel() ?? $code,
                promoCodeValue: $code,
            );
        }

        return $promoCodes;
    }

    public function extractProducts(Cart $cart, SalesChannelContext $context): array
    {
        $productLineItems = $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productIds = array_filter($productLineItems->getReferenceIds());
        $descriptions = $this->loadProductDescriptions($productIds, $context);
        $productImages = $this->loadProductMedia($productIds, $context);

        $products = [];

        foreach ($productLineItems as $lineItem) {
            $lineItemPrice = $lineItem->getPrice();
            if ($lineItemPrice === null) {
                continue;
            }

            $itemRounding = $context->getItemRounding();
            $quantity = $lineItemPrice->getQuantity();
            $totalTaxAmount = $lineItemPrice->getCalculatedTaxes()->getAmount();

            $unitTaxAmount = $this->cashRounding->mathRound(
                price: $totalTaxAmount / $quantity,
                config: $itemRounding
            );
            $unitGrossPrice = $lineItemPrice->getUnitPrice();
            $unitNetPrice = $unitGrossPrice - $unitTaxAmount;

            $price = Money::fromShopwarePrice(
                net: $unitNetPrice,
                gross: $unitGrossPrice
            );

            $netRatio = $unitGrossPrice > 0 ? $unitNetPrice / $unitGrossPrice : 1.0;

            $lowestPrice = null;
            $regulation = $lineItemPrice->getRegulationPrice();
            if ($regulation !== null && $regulation->getPrice() > 0) {
                $regGross = $regulation->getPrice();
                $lowestPrice = Money::fromShopwarePrice(net: $regGross * $netRatio, gross: $regGross);
            }

            $basePrice = $price;
            $promoPrice = null;
            $listPrice = $lineItemPrice->getListPrice();
            if ($listPrice !== null && $listPrice->getPrice() > $unitGrossPrice) {
                $listGross = $listPrice->getPrice();
                $basePrice = Money::fromShopwarePrice(net: $listGross * $netRatio, gross: $listGross);
                $promoPrice = $price;
            }

            if ($lowestPrice !== null && $promoPrice !== null && $lowestPrice->getGross() < $promoPrice->getGross()) {
                $lowestPrice = null;
            }

            $deliveryInfo = $lineItem->getDeliveryInformation();
            $stock = $deliveryInfo?->getStock();
            $payload = $lineItem->getPayload();
            $isCloseout = $payload['isCloseout'] ?? false;

            if (!$isCloseout) {
                $availableStock = 9999;
            } else {
                $availableStock = ($stock !== null && $stock > 0) ? $stock : 1;
            }

            $quantityInfo = $lineItem->getQuantityInformation();
            $minPurchase = $quantityInfo?->getMinPurchase() ?? 1;
            $maxPurchase = $quantityInfo?->getMaxPurchase();
            $purchaseSteps = $quantityInfo?->getPurchaseSteps() ?? 1;

            $maxQuantity = $maxPurchase !== null
                ? min($maxPurchase, $availableStock)
                : $availableStock;

            $quantity = new ProductQuantity(
                quantity: $lineItem->getQuantity(),
                quantityType: QuantityType::INTEGER,
                quantityUnit: ProductQuantity::PIECE,
                availableQuantity: $availableStock,
                minQuantity: $minPurchase,
                maxQuantity: $maxQuantity,
                quantityJump: $purchaseSteps > 1 ? $purchaseSteps : null,
            );

            $productImage = null;
            $cover = $lineItem->getCover();
            if ($cover !== null && !empty($cover->getUrl())) {
                $productImage = $this->normalizeMediaUrl($cover->getUrl());
            }

            $productAttributes = $this->extractProductAttributes($payload);
            $isDigital = $lineItem->hasState(State::IS_DOWNLOAD);

            $product = new BasketProduct(
                productId: $lineItem->getId(),
                productName: $lineItem->getLabel() ?? 'Unknown Product',
                basePrice: $basePrice,
                quantity: $quantity,
                productType: $isDigital ? ProductType::DIGITAL : ProductType::PRODUCT,
                productCategory: null,
                ean: null,
                productDescription: $descriptions[$lineItem->getReferencedId()] ?? null,
                productLink: $this->productUrlGenerator->generateUrl($lineItem->getReferencedId(), $context),
                productImage: $productImage,
                additionalProductImages: $productImages[$lineItem->getReferencedId()] ?? [],
                promoPrice: $promoPrice,
                lowestPrice: $lowestPrice,
                productAttributes: $productAttributes,
                deliveryProduct: [],
            );

            $products[] = $product;
        }

        return $products;
    }

    public function extractProductsToOrderLines(Cart $cart, SalesChannelContext $context): array
    {
        $productLineItems = $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productIds = array_filter($productLineItems->getReferenceIds());
        $descriptions = $this->loadProductDescriptions($productIds, $context);

        $orderLines = [];

        foreach ($productLineItems as $lineItem) {
            $lineItemPrice = $lineItem->getPrice();
            if ($lineItemPrice === null) {
                continue;
            }
            $productId = $lineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }

            $itemRounding = $context->getItemRounding();
            $qty = $lineItemPrice->getQuantity();
            $totalTaxAmount = $lineItemPrice->getCalculatedTaxes()->getAmount();

            $unitTaxAmount = $this->cashRounding->mathRound(
                price: $totalTaxAmount / $qty,
                config: $itemRounding
            );
            $unitGrossPrice = $lineItemPrice->getUnitPrice();
            $unitNetPrice = $unitGrossPrice - $unitTaxAmount;

            $basePrice = Money::fromShopwarePrice(
                net: $unitNetPrice,
                gross: $unitGrossPrice
            );

            $quantityVO = new Quantity(
                value: (float) $lineItem->getQuantity(),
                unit: 'pcs',
                type: QuantityType::INTEGER,
            );

            $payload = $lineItem->getPayload();
            $categoryIds = $payload['categoryIds'] ?? [];
            $firstCategoryId = !empty($categoryIds) ? (string) $categoryIds[0] : null;

            $imageUrl = null;
            $cover = $lineItem->getCover();
            if ($cover !== null) {
                $imageUrl = $cover->getUrl();
            }

            $orderLine = new OrderLine(
                productId: $productId,
                name: $lineItem->getLabel() ?? 'Unknown Product',
                type: $lineItem->hasState(State::IS_DOWNLOAD)
                    ? ProductType::DIGITAL
                    : ProductType::PRODUCT,
                quantity: $quantityVO,
                basePrice: $basePrice,
                ean: null,
                category: $firstCategoryId,
                description: $descriptions[$productId] ?? null,
                imageUrl: $imageUrl,
                productUrl: $this->productUrlGenerator->generateUrl($productId, $context),
            );

            $orderLines[] = $orderLine;
        }

        return $orderLines;
    }

    public function calculateOrderPricing(Cart $cart, SalesChannelContext $context): OrderPricing
    {
        $price = $cart->getPrice();

        $deliveries = $cart->getDeliveries();
        $costs = $this->sumDeliveryCosts($deliveries);
        $deliveryNetTotal = $costs['net'];
        $deliveryGrossTotal = $costs['gross'];

        $deliveryPrice = Money::fromShopwarePrice(
            net: $deliveryNetTotal,
            gross: $deliveryGrossTotal
        );

        $finalPrice = Money::fromShopwarePrice(
            net: $price->getNetPrice(),
            gross: $price->getTotalPrice()
        );

        // InPost app renders "Dostawa" = final - base on the order details screen,
        // so order_base_price must already net out cart-level promotions; otherwise
        // a promo larger than the shipping cost shows as a negative delivery row.
        $basePrice = Money::fromShopwarePrice(
            net: $price->getNetPrice() - $deliveryNetTotal,
            gross: $price->getTotalPrice() - $deliveryGrossTotal,
        );

        $discountGross = 0.0;
        foreach ($cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE) as $promo) {
            $promoPrice = $promo->getPrice();
            if ($promoPrice !== null) {
                $discountGross += $promoPrice->getTotalPrice();
            }
        }

        return new OrderPricing(
            base: $basePrice,
            delivery: $deliveryPrice,
            final: $finalPrice,
            discount: $discountGross !== 0.0 ? round(abs($discountGross), 2) : null,
        );
    }

    /**
     * @param BasketProduct[] $products
     */
    private function containsDigitalProduct(array $products): bool
    {
        foreach ($products as $product) {
            if ($product->isDigital()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, \Shopware\Core\Checkout\Shipping\ShippingMethodEntity> $shippingMethodsById
     */
    private function resolveBaseDeliveryPrice(
        Cart $cart,
        string $baseMethodId,
        array $shippingMethodsById,
        SalesChannelContext $context,
        SalesChannelContext $pricingContext,
    ): ?Money {
        // Verify the base method is available first, so a stale cart delivery cannot
        // "revive" a method that is no longer offered (onlyAvailable list).
        $shippingMethod = $shippingMethodsById[$baseMethodId] ?? null;
        if ($shippingMethod === null) {
            return null;
        }

        // Same instance = nothing to neutralise; keep the original behaviour.
        $neutralisedForCod = $pricingContext !== $context;

        $cartMatchingDeliveries = $this->collectCartDeliveriesByShippingMethodIds($cart, [$baseMethodId]);

        if ($cartMatchingDeliveries->count() > 0) {
            if (!$neutralisedForCod) {
                $costs = $this->sumDeliveryCosts($cartMatchingDeliveries);

                return Money::fromShopwarePrice(net: $costs['net'], gross: $costs['gross']);
            }

            // Re-price real deliveries under the non-COD context (cloned, live cart
            // untouched); keep promotion deliveries (negative cost) as-is so a shipping
            // discount is not lost - SUEZ-1045.
            $toReprice = new DeliveryCollection();
            $promoDeliveries = new DeliveryCollection();
            foreach ($cartMatchingDeliveries as $delivery) {
                ($delivery->getShippingCosts()->getTotalPrice() < 0 ? $promoDeliveries : $toReprice)->add($delivery);
            }

            $deliveries = $this->cloneDeliveriesWithResetCosts($toReprice);
            $data = new CartDataCollection();
            $data->set(DeliveryProcessor::buildKey($baseMethodId), $shippingMethod);
            $this->deliveryCalculator->calculate($data, $cart, $deliveries, $pricingContext);

            // Add promotions back so the discount nets against the repriced base in a
            // single sum, before sumDeliveryCosts clamps the total to zero.
            foreach ($promoDeliveries as $promoDelivery) {
                $deliveries->add($promoDelivery);
            }

            $costs = $this->sumDeliveryCosts($deliveries);

            return Money::fromShopwarePrice(net: $costs['net'], gross: $costs['gross']);
        }

        $deliveries = $this->deliveryBuilder->buildByUsingShippingMethod($cart, $shippingMethod, $pricingContext);
        if ($deliveries->count() === 0) {
            return null;
        }

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($baseMethodId), $shippingMethod);
        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $pricingContext);

        $this->applyDeliveryPromotions($cart, $deliveries, $data, $pricingContext);

        $costs = $this->sumDeliveryCosts($deliveries);

        return Money::fromShopwarePrice(net: $costs['net'], gross: $costs['gross']);
    }

    /**
     * Clones deliveries with zeroed cost so DeliveryCalculator re-matches the price
     * tier instead of reusing the existing cost; originals stay untouched.
     */
    private function cloneDeliveriesWithResetCosts(DeliveryCollection $deliveries): DeliveryCollection
    {
        $clones = new DeliveryCollection();

        foreach ($deliveries as $delivery) {
            $clone = clone $delivery;
            $clone->setShippingCosts(
                new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection())
            );
            $clones->add($clone);
        }

        return $clones;
    }

    /**
     * Returns the cart's existing deliveries whose shipping method id matches any of
     * the provided ids. Negative shipping cost deliveries are included on purpose:
     * PromotionDeliveryCalculator adds them (with the same shipping method) for
     * delivery-scope promotions, so summing them in nets the discount off the price.
     *
     * Plugin-agnostic: works with the native Shopware DeliveryCollection. If a third
     * party plugin produced multiple deliveries (e.g. partial delivery splits), they
     * are all included and summed; if the cart has one delivery only, the result is
     * still consistent with the previous behaviour.
     *
     * @param string[] $shippingMethodIds
     */
    private function collectCartDeliveriesByShippingMethodIds(Cart $cart, array $shippingMethodIds): DeliveryCollection
    {
        $collected = new DeliveryCollection();

        if ($shippingMethodIds === []) {
            return $collected;
        }

        $idSet = array_flip($shippingMethodIds);

        foreach ($cart->getDeliveries() as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            if (!isset($idSet[$shippingMethod->getId()])) {
                continue;
            }

            $collected->add($delivery);
        }

        return $collected;
    }

    /**
     * Sum shipping costs across all deliveries (PartialDelivery splits cart into multiple deliveries).
     *
     * @return array{net: float, gross: float}
     */
    private function sumDeliveryCosts(?DeliveryCollection $deliveries): array
    {
        if ($deliveries === null || $deliveries->count() === 0) {
            return ['net' => 0.0, 'gross' => 0.0];
        }

        $net = 0.0;
        $gross = 0.0;

        foreach ($deliveries as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $totalPrice = $shippingCosts->getTotalPrice();
            // Negative deliveries are the shipping discounts that PromotionDeliveryCalculator
            // adds for delivery-scope promotions (same shipping method, negative cost). They
            // must be summed in so the reported price reflects the promotion.
            $gross += $totalPrice;
            $net += $totalPrice - $shippingCosts->getCalculatedTaxes()->getAmount();
        }

        // A promotion can never reduce shipping below zero, so clamp the sum to 0.
        return ['net' => max(0.0, $net), 'gross' => max(0.0, $gross)];
    }

    /**
     * Calculate basket prices:
     * - base: sum of product prices (without cart-level promotions)
     * - promo: base + automatic promotions (without promo codes)
     * - final: base + all promotions (automatic + promo codes)
     */
    private function calculateBasketPrices(Cart $cart, SalesChannelContext $context): BasketPricing
    {
        $itemRounding = $context->getItemRounding();

        // 1. Calculate base_price - sum of product prices (without cart-level promotions)
        $baseGrossTotal = 0.0;
        $baseNetTotal = 0.0;

        $productLineItems = $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        foreach ($productLineItems as $lineItem) {
            $lineItemPrice = $lineItem->getPrice();
            if ($lineItemPrice === null) {
                continue;
            }
            $totalTaxAmount = $lineItemPrice->getCalculatedTaxes()->getAmount();
            $baseGrossTotal += $lineItemPrice->getTotalPrice();
            $baseNetTotal += $lineItemPrice->getTotalPrice() - $totalTaxAmount;
        }

        // 2. Calculate discounts from cart-level promotions
        $autoDiscountGross = 0.0;  // automatic promotions (without code)
        $autoDiscountNet = 0.0;
        $codeDiscountGross = 0.0;  // promotions with code
        $codeDiscountNet = 0.0;

        $promoLineItems = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        foreach ($promoLineItems as $promo) {
            $promoPrice = $promo->getPrice();
            if ($promoPrice === null) {
                continue;
            }

            // Promotion prices are negative (discounts)
            $discountGross = $promoPrice->getTotalPrice();
            $discountNet = $discountGross - $promoPrice->getCalculatedTaxes()->getAmount();

            $payload = $promo->getPayload();
            if (!empty($payload['code'])) {
                // Promotion with code
                $codeDiscountGross += $discountGross;
                $codeDiscountNet += $discountNet;
            } else {
                // Automatic promotion (no code)
                $autoDiscountGross += $discountGross;
                $autoDiscountNet += $discountNet;
            }
        }

        // 3. Calculate final prices
        // promo_price = base_price + automatic discounts
        $promoGrossTotal = $baseGrossTotal + $autoDiscountGross;
        $promoNetTotal = $baseNetTotal + $autoDiscountNet;

        // final_price = base_price + all discounts (automatic + code)
        $finalGrossTotal = $baseGrossTotal + $autoDiscountGross + $codeDiscountGross;
        $finalNetTotal = $baseNetTotal + $autoDiscountNet + $codeDiscountNet;

        // Round all values
        $basePrice = Money::fromShopwarePrice(
            net: $this->cashRounding->mathRound($baseNetTotal, $itemRounding),
            gross: $this->cashRounding->mathRound($baseGrossTotal, $itemRounding)
        );

        $promoPrice = Money::fromShopwarePrice(
            net: $this->cashRounding->mathRound($promoNetTotal, $itemRounding),
            gross: $this->cashRounding->mathRound($promoGrossTotal, $itemRounding)
        );

        $finalPrice = Money::fromShopwarePrice(
            net: $this->cashRounding->mathRound($finalNetTotal, $itemRounding),
            gross: $this->cashRounding->mathRound($finalGrossTotal, $itemRounding)
        );

        return new BasketPricing(
            base: $basePrice,
            promo: $promoPrice,
            final: $finalPrice,
        );
    }

    /**
     * Build additional delivery options (COD, PWW) for a delivery type.
     *
     * @return DeliveryAdditionalOption[]
     */
    private function buildAdditionalOptions(DeliveryType $deliveryType, SalesChannelContext $context): array
    {
        $additionalOptions = [];
        $salesChannelId = $context->getSalesChannel()->getId();
        $now = new DateTimeImmutable();

        $availableServices = $this->serviceOptionsProvider->getAvailableServicesForDeliveryType(
            $deliveryType,
            $salesChannelId
        );

        foreach ($availableServices as $serviceOptions) {
            // Check time-based availability (for PWW)
            if (!$serviceOptions->isAvailable($now)) {
                continue;
            }

            // Calculate price (gross -> net with 23% VAT)
            $price = Money::zero();
            if ($serviceOptions->hasAdditionalCost()) {
                $vatRate = 0.23;
                $gross = $serviceOptions->additionalCostGross;
                $net = $gross / (1 + $vatRate);
                $price = Money::fromShopwarePrice(net: $net, gross: $gross);
            }

            $additionalOptions[] = new DeliveryAdditionalOption(
                deliveryName: $this->serviceNameTranslator->getName($serviceOptions->serviceCode),
                deliveryCodeValue: $serviceOptions->serviceCode->value,
                deliveryOptionPrice: $price,
            );
        }

        return $additionalOptions;
    }

    private function applyDeliveryPromotions(
        Cart $cart,
        DeliveryCollection $deliveries,
        CartDataCollection $data,
        SalesChannelContext $context,
    ): void {
        $promotionLineItems = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        if ($promotionLineItems->count() === 0) {
            return;
        }

        $deliveryPromotions = $this->rebuildDeliveryPromotionLineItems($promotionLineItems);
        if ($deliveryPromotions->count() === 0) {
            return;
        }

        $data->set(PromotionProcessor::DATA_KEY, $deliveryPromotions);

        $tempCart = new Cart($cart->getToken());
        $tempCart->setLineItems(new LineItemCollection($cart->getLineItems()->getElements()));
        $tempCart->setDeliveries($deliveries);
        $tempCart->setPrice($cart->getPrice());
        $tempCart->setRuleIds($cart->getRuleIds());

        try {
            $this->promotionDeliveryProcessor->process(
                $data,
                $tempCart,
                $tempCart,
                $context,
                new CartBehavior($context->getPermissions()),
            );
        } catch (Throwable $e) {
            $codes = array_map(
                static fn (LineItem $item): ?string => $item->getReferencedId(),
                $deliveryPromotions->getElements(),
            );
            $this->logger->warning(
                'InPost Pay: PromotionDeliveryProcessor failed, falling back to base shipping price',
                [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'codes' => array_values($codes),
                ],
            );
        }
    }

    /**
     * Zwraca line itemy promocji ze scope=delivery z PriceDefinition zgodnym z payload['discountType'].
     * Jesli PriceDefinition na oryginalnym item jest niezgodny (lub null), odbudowuje go z payload['value']
     * — zalozenie single currency (PLN), wiec surowa wartosc payloadu jest finalna kwota rabatu.
     */
    private function rebuildDeliveryPromotionLineItems(LineItemCollection $promotionLineItems): LineItemCollection
    {
        $rebuilt = [];

        foreach ($promotionLineItems as $item) {
            if ($item->getPayloadValue('discountScope') !== PromotionDiscountEntity::SCOPE_DELIVERY) {
                continue;
            }

            $discountType = $item->getPayloadValue('discountType');
            $expectedClass = match ($discountType) {
                PromotionDiscountEntity::TYPE_ABSOLUTE, PromotionDiscountEntity::TYPE_FIXED_UNIT => AbsolutePriceDefinition::class,
                PromotionDiscountEntity::TYPE_PERCENTAGE => PercentagePriceDefinition::class,
                default => null,
            };

            if ($expectedClass === null) {
                $this->logger->warning(
                    'InPost Pay: skipping delivery promotion with unknown discountType',
                    ['code' => $item->getReferencedId(), 'discountType' => $discountType],
                );

                continue;
            }

            $currentDefinition = $item->getPriceDefinition();
            $clone = clone $item;

            if (!$currentDefinition instanceof $expectedClass) {
                $value = -abs((float) $item->getPayloadValue('value'));
                $newDefinition = $expectedClass === PercentagePriceDefinition::class
                    ? new PercentagePriceDefinition($value)
                    : new AbsolutePriceDefinition($value);

                $clone->setPriceDefinition($newDefinition);

                $this->logger->info(
                    'InPost Pay: rebuilt PriceDefinition for delivery promotion',
                    [
                        'code' => $item->getReferencedId(),
                        'discountType' => $discountType,
                        'value' => $value,
                    ],
                );
            }

            $rebuilt[] = $clone;
        }

        return new LineItemCollection($rebuilt);
    }

    private function getPaymentTypes(SalesChannelContext $context): array
    {
        $paymentTypesArray = $this->configProvider->getActivePaymentMethods(salesChannelContext: $context);

        if (!is_array($paymentTypesArray)) {
            return [];
        }

        $paymentTypes = [];
        foreach ($paymentTypesArray as $paymentType) {
            if (!is_string($paymentType) || $paymentType === '') {
                continue;
            }

            try {
                $paymentTypes[] = PaymentType::getEnum(paymentTypeName: $paymentType);
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $paymentTypes;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return ProductAttribute[]
     */
    private function extractProductAttributes(array $payload): array
    {
        $productAttributes = [];
        $options = $payload['options'] ?? [];

        foreach ($options as $option) {
            $groupName = $option['group'] ?? '';
            $optionValue = $option['option'] ?? '';

            if ($groupName !== '' && $optionValue !== '') {
                $productAttributes[] = new ProductAttribute(
                    attributeName: $groupName,
                    attributeValue: $optionValue
                );
            }
        }

        return $productAttributes;
    }

    private function loadProductDescriptions(array $productIds, SalesChannelContext $context): array
    {
        if (empty($productIds)) {
            return [];
        }

        $criteria = new Criteria($productIds);
        $products = $this->productRepository->search($criteria, $context->getContext())->getElements();

        $descriptions = [];
        foreach ($products as $product) {
            $translated = $product->getTranslated();
            $descriptions[$product->getId()] = $translated['description'] ?? $product->getDescription();
        }

        return $descriptions;
    }

    /**
     * @param string[] $productIds
     *
     * @return array<string, ProductImage[]>
     */
    private function loadProductMedia(array $productIds, SalesChannelContext $context): array
    {
        if (empty($productIds)) {
            return [];
        }

        $criteria = new Criteria($productIds);
        $criteria->addAssociation('media');
        $criteria->getAssociation('media')->addSorting(new FieldSorting('position'));

        $products = $this->productRepository->search($criteria, $context->getContext())->getEntities();

        $imagesByProduct = [];
        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $media = $product->getMedia();
            if ($media === null) {
                continue;
            }

            $coverId = $product->getCoverId();
            $images = [];

            foreach ($media as $productMedia) {
                if ($coverId !== null && $productMedia->getId() === $coverId) {
                    continue;
                }

                $mediaEntity = $productMedia->getMedia();
                $normal = $this->normalizeMediaUrl($mediaEntity?->getUrl() ?? '');

                $smallUrl = $mediaEntity?->getUrl();
                $thumbnails = $mediaEntity?->getThumbnails();
                if ($thumbnails !== null && $thumbnails->count() > 0) {
                    $smallest = null;
                    foreach ($thumbnails as $thumbnail) {
                        if ($smallest === null || $thumbnail->getWidth() < $smallest->getWidth()) {
                            $smallest = $thumbnail;
                        }
                    }
                    if ($smallest !== null) {
                        $smallUrl = $smallest->getUrl();
                    }
                }
                $small = $this->normalizeMediaUrl($smallUrl ?? '');

                if ($normal !== null && $small !== null) {
                    $images[] = new ProductImage(smallSize: $small, normalSize: $normal);
                }

                if (count($images) >= 10) {
                    break;
                }
            }

            $imagesByProduct[$product->getId()] = $images;
        }

        return $imagesByProduct;
    }

    private function normalizeMediaUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $encodedPath = '';
        if (isset($parts['path'])) {
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $parts['path'])));
        }

        $rebuilt = $parts['scheme'] . '://'
            . (isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '')
            . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $encodedPath
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        return filter_var($rebuilt, FILTER_VALIDATE_URL) ? $rebuilt : null;
    }
}
