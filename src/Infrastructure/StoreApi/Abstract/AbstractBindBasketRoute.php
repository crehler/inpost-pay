<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\StoreApi\Abstract;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};

abstract class AbstractBindBasketRoute
{
    abstract public function bindBasket(Request $request, SalesChannelContext $context): JsonResponse;

    abstract public function getBindingStatus(Request $request, SalesChannelContext $context): JsonResponse;

    abstract public function getOrderConfirmationUrl(SalesChannelContext $context): JsonResponse;

    abstract public function getDecorated(): self;
}
