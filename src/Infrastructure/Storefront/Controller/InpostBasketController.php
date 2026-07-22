<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Storefront\Controller;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Infrastructure\StoreApi\Abstract\AbstractBindBasketRoute;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class InpostBasketController extends StorefrontController
{
    public function __construct(
        private readonly AbstractBindBasketRoute $bindBasketRoute,
        private readonly InpostBasketSessionService $basketSessionService,
    ) {
    }

    #[Route(
        path: '/checkout/inpost/basket/bind',
        name: 'frontend.inpost.basket.bind',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function bindBasket(Request $request, SalesChannelContext $context): JsonResponse
    {
        return $this->bindBasketRoute->bindBasket($request, $context);
    }

    #[Route(
        path: '/checkout/inpost/basket/status',
        name: 'frontend.inpost.basket.status',
        defaults: ['XmlHttpRequest' => true],
        methods: ['GET']
    )]
    public function getBindingStatus(Request $request, SalesChannelContext $context): JsonResponse
    {
        return $this->bindBasketRoute->getBindingStatus($request, $context);
    }

    #[Route(
        path: '/checkout/inpost/order/confirmation-url',
        name: 'frontend.inpost.order.confirmation_url',
        defaults: ['XmlHttpRequest' => true],
        methods: ['GET']
    )]
    public function getOrderConfirmationUrl(SalesChannelContext $context): JsonResponse
    {
        return $this->bindBasketRoute->getOrderConfirmationUrl($context);
    }
}
