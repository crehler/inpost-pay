<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Serializer;

use Crehler\InpostPay\Domain\Aggregate\InpostBasket;

use function array_map;

final readonly class InpostBasketSerializer
{
    public function toInpostApiResponse(InpostBasket $basket): array
    {
        $response = [
            'summary' => $basket->getSummary()->toArray(),
            'delivery' => array_map(
                fn ($option) => $option->toArray(),
                $basket->getDeliveryOptions()
            ),
            'products' => array_map(
                fn ($product) => $product->toArray(),
                $basket->getProducts()
            ),
            'consents' => $basket->getConsents(),
            'promo_codes' => array_map(
                fn ($promo) => $promo->toArray(),
                $basket->getPromoCodes()
            ),
        ];

        $relatedProducts = $basket->getRelatedProducts();
        if (!empty($relatedProducts)) {
            $response['related_products'] = array_map(
                fn ($product) => $product->toArray(),
                $relatedProducts
            );
        }

        return $response;
    }

    public function toArray(InpostBasket $basket): array
    {
        return [
            'basket_id' => $basket->getBasketId(),
            'inpost_basket_id' => $basket->getInpostBasketId(),
            'status' => $basket->getStatus()->value,
            'phone_number' => $basket->getPhoneNumber()->toArray(),
            'browser_info' => $basket->getBrowserInfo()->toArray(),
            'masked_phone_number' => $basket->getMaskedPhoneNumber(),
            'name' => $basket->getName(),
            'surname' => $basket->getSurname(),
            'summary' => $basket->getSummary()->toArray(),
            'delivery' => array_map(
                fn ($option) => $option->toArray(),
                $basket->getDeliveryOptions()
            ),
            'products' => array_map(
                fn ($product) => $product->toArray(),
                $basket->getProducts()
            ),
            'confirmed_at' => $basket->getConfirmedAt()->format('c'),
            'is_expired' => $basket->getSummary()->isExpired(),
            'products_count' => $basket->getProductsCount(),
            'has_physical_products' => $basket->hasPhysicalProducts(),
            'has_digital_products' => $basket->hasDigitalProducts(),
            'products_total_price' => $basket->getProductsTotalPrice()->toArray(),
            'total_price' => $basket->getTotalPrice()->toArray(),
        ];
    }
}
