<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Enum;

enum BindingPlace: string
{
    case ProductCard = 'PRODUCT_CARD';
    case CheckoutPage = 'CHECKOUT_PAGE';
    case MiniCartPage = 'MINICART_PAGE';
    case BasketSummary = 'BASKET_SUMMARY';
    case RegisterPage = 'REGISTERFORM_PAGE';

    public function getConfigPrefix(): string
    {
        return match ($this) {
            self::ProductCard => 'productCard',
            self::CheckoutPage => 'checkoutPage',
            self::MiniCartPage => 'miniCartPage',
            self::BasketSummary => 'basketSummary',
            self::RegisterPage => 'registerPage',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ProductCard => 'Product Card',
            self::CheckoutPage => 'Checkout Page',
            self::MiniCartPage => 'Mini Cart (Offcanvas)',
            self::BasketSummary => 'Cart Summary Page',
            self::RegisterPage => 'Register Page',
        };
    }
}
