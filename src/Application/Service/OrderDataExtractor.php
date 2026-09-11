<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\Aggregate\Order;
use Crehler\InpostPay\Domain\Entity\OrderLine;
use Crehler\InpostPay\Domain\ValueObject\{Address, DeliveryType, Money, PaymentType, PhoneNumber, ProductType, Quantity, QuantityType};
use Crehler\InpostPay\Domain\ValueObject\Order\{CustomerInfo, DeliveryDetails, InvoiceDetails, LegalForm, OrderPricing};
use Crehler\InpostPay\Infrastructure\Logger\ExceptionLogger;
use Crehler\InpostPay\Infrastructure\Persistence\Repository\ShopwareOrderRepository;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use Crehler\InpostPay\Infrastructure\Service\ProductUrlGenerator;
use RuntimeException;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Throwable;

use function array_map;
use function count;
use function explode;
use function filter_var;
use function implode;
use function max;
use function parse_url;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;

readonly class OrderDataExtractor
{
    public function __construct(
        private ShopwareOrderRepository $shopwareOrderRepository,
        private InpostPayConfigProvider $configProvider,
        private InpostBasketSessionService $basketSessionService,
        private CashRounding $cashRounding,
        private ProductUrlGenerator $productUrlGenerator,
        private ExceptionLogger $exceptionLogger,
        private StatusLabelResolver $statusLabelResolver,
    ) {
    }

    public function mapShopwareOrderToDomain(OrderEntity $shopwareOrder): Order
    {
        $context = Context::createDefaultContext();

        $widgetConfig = $this->configProvider->getWidgetConfig();
        $posId = $widgetConfig->postId ?? '';

        $paymentType = $this->mapPaymentMethod($shopwareOrder);

        $statusDescription = $this->statusLabelResolver->transactionLabel(
            $shopwareOrder->getStateMachineState()->getTechnicalName(),
            $shopwareOrder->getSalesChannelId()
        );

        $basketId = $this->extractBasketId($shopwareOrder);

        $currency = $shopwareOrder->getCurrency()->getIsoCode();

        $customerInfo = $this->extractCustomerInfo($shopwareOrder);

        $deliveryDetails = $this->extractDeliveryDetails($shopwareOrder);

        $invoiceDetails = $this->extractInvoiceDetails($shopwareOrder);

        $orderLines = $this->extractOrderLines($shopwareOrder, $context);

        $pricing = $this->calculateOrderPricing($shopwareOrder, $context);

        $consents = $this->extractConsents($shopwareOrder);

        $trackingCodes = $this->extractTrackingCodes($shopwareOrder);

        return new Order(
            id: $shopwareOrder->getId(),
            merchantBasketId: $basketId,
            merchantPosId: $posId,
            status: $shopwareOrder->getStateMachineState()->getTechnicalName(),
            currency: $currency,
            createdAt: $shopwareOrder->getOrderDateTime(),
            customer: $customerInfo,
            delivery: $deliveryDetails,
            pricing: $pricing,
            paymentType: $paymentType,
            items: $orderLines,
            consents: $consents,
            additionalParams: [],
            invoice: $invoiceDetails,
            comments: $shopwareOrder->getCustomerComment(),
            deliveryReferences: $trackingCodes,
            merchantStatusDesc: $statusDescription,
            customerOrderId: $shopwareOrder->getOrderNumber(),
        );
    }

    /**
     * Resolve the customer phone for outgoing notifications.
     * Returns null instead of throwing when the order has no usable phone number,
     * because phone_number is optional in the InPost order-event payload.
     */
    public function getCustomerPhone(OrderEntity $order): ?PhoneNumber
    {
        $phoneNumberStr = $order->getBillingAddress()?->getPhoneNumber()
            ?? $order->getOrderCustomer()?->getPhoneNumber()
            ?? '';

        if (trim($phoneNumberStr) === '') {
            return null;
        }

        try {
            return $this->extractPhoneNumber($phoneNumberStr);
        } catch (Throwable) {
            return null;
        }
    }

    public function extractTrackingCodes(OrderEntity $order): array
    {
        $codes = [];

        foreach ($order->getDeliveries() as $delivery) {
            foreach ($delivery->getTrackingCodes() as $trackingCode) {
                $codes[] = $trackingCode;
            }
        }

        return $codes;
    }

    private function sumOrderDeliveryCosts(OrderDeliveryCollection $deliveries): array
    {
        $net = 0.0;
        $gross = 0.0;

        foreach ($deliveries as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $totalPrice = $shippingCosts->getTotalPrice();
            // Negative deliveries are shipping discounts (delivery-scope promotions); sum them
            // in so the order payload reports the discounted shipping the customer actually pays.
            $gross += $totalPrice;
            $net += $totalPrice - $shippingCosts->getCalculatedTaxes()->getAmount();
        }

        // A promotion can never reduce shipping below zero, so clamp the sum to 0.
        return ['net' => max(0.0, $net), 'gross' => max(0.0, $gross)];
    }

    private function extractCustomerInfo(OrderEntity $order): CustomerInfo
    {
        $orderCustomer = $order->getOrderCustomer();
        $billingAddress = $order->getBillingAddress();

        $phoneNumberStr = $billingAddress?->getPhoneNumber() ?? $orderCustomer?->getPhoneNumber() ?? '';
        $phoneNumber = $this->extractPhoneNumber($phoneNumberStr);

        $clientAddress = new Address(
            countryCode: $billingAddress->getCountry()->getIso(),
            city: $billingAddress->getCity(),
            postalCode: $billingAddress->getZipcode(),
            streetLine: $billingAddress->getStreet(),
        );

        return new CustomerInfo(
            firstName: $orderCustomer->getFirstName(),
            lastName: $orderCustomer->getLastName(),
            email: $orderCustomer->getEmail(),
            phone: $phoneNumber,
            address: $clientAddress,
        );
    }

    private function extractDeliveryDetails(OrderEntity $order): DeliveryDetails
    {
        $deliveries = $order->getDeliveries();
        if ($deliveries->count() === 0) {
            throw new RuntimeException('Order has no deliveries');
        }

        $delivery = $deliveries->first();
        $shippingAddress = $delivery->getShippingOrderAddress();
        $shippingMethod = $delivery->getShippingMethod();

        $deliveryType = $this->mapShippingMethodToDeliveryType($shippingMethod->getName() ?? '');

        $costs = $this->sumOrderDeliveryCosts($deliveries);
        $deliveryPrice = Money::fromShopwarePrice(
            net: $costs['net'],
            gross: $costs['gross']
        );

        $billingAddress = $order->getBillingAddress();
        $phoneNumberStr = $shippingAddress->getPhoneNumber() ?? $billingAddress?->getPhoneNumber() ?? '';
        $phoneNumber = $this->extractPhoneNumber($phoneNumberStr);

        $deliveryAddress = new Address(
            countryCode: $shippingAddress->getCountry()->getIso(),
            city: $shippingAddress->getCity(),
            streetLine: $shippingAddress->getStreet(),
            postalCode: $shippingAddress->getZipcode(),
        );

        $trackingCodes = $this->extractTrackingCodes($order);

        return new DeliveryDetails(
            type: $deliveryType,
            phone: $phoneNumber,
            deliveryPrice: $deliveryPrice,
            deliveryCodes: $trackingCodes,
            deliveryPoint: null,
            deliveryAddress: $deliveryAddress,
            digitalDeliveryEmail: null,
            mail: null,
            courierNote: null,
        );
    }

    private function extractInvoiceDetails(OrderEntity $order): ?InvoiceDetails
    {
        $billingAddress = $order->getBillingAddress();
        $vatId = $billingAddress?->getVatId();

        if ($billingAddress === null || !$billingAddress->getCompany() || !$vatId) {
            return null;
        }

        return new InvoiceDetails(
            legalForm: LegalForm::COMPANY,
            countryCode: $billingAddress->getCountry()?->getIso() ?? '',
            taxId: $vatId,
            companyName: $billingAddress->getCompany(),
            city: $billingAddress->getCity(),
            street: $billingAddress->getStreet(),
            postalCode: $billingAddress->getZipcode(),
        );
    }

    private function extractOrderLines(OrderEntity $order, Context $context): array
    {
        $lines = [];
        $salesChannelId = $order->getSalesChannelId();

        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== 'product') {
                continue;
            }

            $product = $lineItem->getProduct();
            if (!$product) {
                continue;
            }

            $productImageUrl = $this->extractProductImage($lineItem);

            $lineItemPrice = $lineItem->getPrice();
            $unitGrossPrice = $lineItemPrice->getUnitPrice();
            $unitNetPrice = $unitGrossPrice - ($lineItemPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity());
            $netRatio = $unitGrossPrice > 0 ? $unitNetPrice / $unitGrossPrice : 1.0;

            $unitPrice = Money::fromShopwarePrice(
                net: $unitNetPrice,
                gross: $unitGrossPrice
            );

            $lowestPrice = null;
            $regulation = $lineItemPrice->getRegulationPrice();
            if ($regulation !== null && $regulation->getPrice() > 0) {
                $regGross = $regulation->getPrice();
                $lowestPrice = Money::fromShopwarePrice(net: $regGross * $netRatio, gross: $regGross);
            }

            $basePrice = $unitPrice;
            $promoPrice = null;
            $listPrice = $lineItemPrice->getListPrice();
            if ($listPrice !== null && $listPrice->getPrice() > $unitGrossPrice) {
                $listGross = $listPrice->getPrice();
                $basePrice = Money::fromShopwarePrice(net: $listGross * $netRatio, gross: $listGross);
                $promoPrice = $unitPrice;
            }

            $quantity = new Quantity(
                value: (float) $lineItem->getQuantity(),
                unit: 'pcs',
                type: QuantityType::INTEGER,
            );

            $orderLine = new OrderLine(
                productId: $lineItem->getProductId(),
                name: $lineItem->getLabel(),
                type: ProductType::PRODUCT,
                quantity: $quantity,
                basePrice: $basePrice,
                ean: $product->getEan(),
                category: $this->extractProductCategory($product),
                description: $product->getDescription(),
                imageUrl: $productImageUrl,
                productUrl: $this->productUrlGenerator->generateUrlFromContext($product->getId(), $context, $salesChannelId),
                additionalImages: $this->extractAdditionalImages($product),
                attributes: [],
                promoPrice: $promoPrice,
                lowestPrice: $lowestPrice,
            );

            $lines[] = $orderLine;
        }

        return $lines;
    }

    private function calculateOrderPricing(OrderEntity $order, Context $context): OrderPricing
    {
        $price = $order->getPrice();

        $basePrice = Money::fromShopwarePrice(
            net: $price->getNetPrice(),
            gross: $price->getTotalPrice()
        );

        $finalPrice = Money::fromShopwarePrice(
            net: $price->getNetPrice(),
            gross: $price->getTotalPrice()
        );

        $deliveryPrice = Money::fromShopwarePrice(
            net: $order->getShippingCosts()->getTotalPrice() - $order->getShippingCosts()->getCalculatedTaxes()->getAmount(),
            gross: $order->getShippingCosts()->getTotalPrice()
        );

        $discount = 0.0;

        return new OrderPricing(
            base: $basePrice,
            delivery: $deliveryPrice,
            final: $finalPrice,
            discount: $discount,
        );
    }

    private function extractConsents(OrderEntity $order): array
    {
        return [];
    }

    private function mapPaymentMethod(OrderEntity $order): PaymentType
    {
        $customFields = $order->getCustomFields();

        if (!isset($customFields['inpost_pay_payment_type'])) {
            throw new RuntimeException(sprintf('Payment type not found in order %s customFields', $order->getId()));
        }

        return PaymentType::from($customFields['inpost_pay_payment_type']);
    }

    private function mapShippingMethodToDeliveryType(string $shippingMethodName): DeliveryType
    {
        $name = strtolower($shippingMethodName);

        if (str_contains($name, 'paczkomat') || str_contains($name, 'apm')) {
            return DeliveryType::APM;
        }

        if (str_contains($name, 'digital') || str_contains($name, 'download')) {
            return DeliveryType::DIGITAL;
        }

        return DeliveryType::COURIER;
    }

    private function extractPhoneNumber(string $phoneNumber): PhoneNumber
    {
        $cleanPhone = trim($phoneNumber);

        if (empty($cleanPhone)) {
            throw new RuntimeException('Phone number is required but not provided');
        }

        if (preg_match('/^\+(\d{1,2})(\d+)$/', $cleanPhone, $matches)) {
            return new PhoneNumber(
                countryPrefix: '+' . $matches[1],
                phone: $matches[2],
            );
        }

        return new PhoneNumber(
            countryPrefix: '+48',
            phone: preg_replace('/\s+/', '', $cleanPhone),
        );
    }

    private function extractProductImage(OrderLineItemEntity $lineItem): ?string
    {
        $cover = $lineItem->getCover();
        if ($cover !== null && !empty($cover->getUrl())) {
            return $this->normalizeMediaUrl($cover->getUrl());
        }

        return null;
    }

    /**
     * @return array<int, array{small_size: string, normal_size: string}>
     */
    private function extractAdditionalImages(ProductEntity $product): array
    {
        $media = $product->getMedia();
        if ($media === null) {
            return [];
        }

        $coverId = $product->getCoverId();
        $images = [];

        foreach ($media as $productMedia) {
            if ($coverId !== null && $productMedia->getId() === $coverId) {
                continue;
            }

            $mediaEntity = $productMedia->getMedia();
            $normal = $this->normalizeMediaUrl($mediaEntity?->getUrl() ?? '');
            $small = $this->normalizeMediaUrl($this->resolveSmallMediaUrl($mediaEntity) ?? '');

            if ($normal === null || $small === null) {
                continue;
            }

            $images[] = ['small_size' => $small, 'normal_size' => $normal];

            if (count($images) >= 10) {
                break;
            }
        }

        return $images;
    }

    private function resolveSmallMediaUrl(?MediaEntity $mediaEntity): ?string
    {
        if ($mediaEntity === null) {
            return null;
        }

        $smallUrl = $mediaEntity->getUrl();
        $thumbnails = $mediaEntity->getThumbnails();

        if ($thumbnails !== null && $thumbnails->count() > 0) {
            $smallestWidth = null;
            foreach ($thumbnails as $thumbnail) {
                if ($smallestWidth === null || $thumbnail->getWidth() < $smallestWidth) {
                    $smallestWidth = $thumbnail->getWidth();
                    $smallUrl = $thumbnail->getUrl();
                }
            }
        }

        return $smallUrl;
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

    private function extractProductCategory($product): string
    {
        $categories = $product->getCategories();
        if ($categories && $categories->count() > 0) {
            return $categories->first()->getName() ?? 'General';
        }

        return 'General';
    }

    private function extractBasketId(OrderEntity $order): string
    {
        try {
            $session = $this->basketSessionService->getSessionByOrderId($order->getId());
            if ($session) {
                return $session->getBasketId();
            }
        } catch (Throwable $e) {
            $this->exceptionLogger->warning(
                'Failed to resolve basket session for order, falling back to orderId',
                $e,
                ['order_id' => $order->getId()],
            );
        }

        return $order->getId();
    }
}
