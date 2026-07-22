<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Application\Dto\ApplyPromoCodesResult;
use Crehler\InpostPay\Application\Dto\EventData\QuantityEventData;
use Crehler\InpostPay\Domain\Exception\{BasketSessionNotFoundException, InvalidBasketException};
use Crehler\InpostPay\Infrastructure\Persistence\Entity\InpostBasketSessionEntity;
use Shopware\Core\Checkout\Cart\{AbstractCartPersister, Cart, CartException};
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as ShopwareCartService;
use Shopware\Core\Checkout\Promotion\Cart\Error\{PromotionExcludedError, PromotionNotEligibleError, PromotionNotFoundError};
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\{SalesChannelContextPersister, SalesChannelContextService, SalesChannelContextServiceInterface, SalesChannelContextServiceParameters};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;

use function implode;
use function max;
use function sprintf;

readonly class CartOperationService
{
    public function __construct(
        private InpostBasketSessionService $basketSessionService,
        private SalesChannelContextServiceInterface $salesChannelContextService,
        private SalesChannelContextPersister $salesChannelContextPersister,
        private AbstractCartPersister $cartPersister,
        private ShopwareCartService $cartService,
    ) {
    }

    public function loadCartByBasketId(string $basketId, array $contextStates = []): Cart
    {
        $session = $this->basketSessionService->getSessionByBasketId($basketId);

        if (!$session) {
            throw BasketSessionNotFoundException::forBasketId($basketId);
        }

        // The cart lives under the current Shopware token, which can differ from the
        // InPost-facing basketId after a context-token change (e.g. guest login).
        // Load it under cartToken; fall back to basketId for rows created before the
        // cart_token column existed.
        $cartToken = $session->getCartToken() ?? $session->getBasketId();

        $context = $this->createSalesChannelContext($cartToken, $session->getSalesChannelId());

        foreach ($contextStates as $state) {
            $context->getContext()->addState($state);
        }

        try {
            return $this->cartPersister->load($cartToken, $context);
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Cannot load cart %s: %s', $cartToken, $e->getMessage()), previous: $e);
        }
    }

    /**
     * Builds a SalesChannelContext for an existing InPost session, using the live
     * Shopware cart token (cartToken) rather than the immutable InPost basketId.
     * After a context-token change the two diverge; loading context/cart under the
     * basketId would target a stale or empty cart.
     */
    public function createSalesChannelContextForSession(InpostBasketSessionEntity $session): SalesChannelContext
    {
        return $this->createSalesChannelContext(
            $session->getCartToken() ?? $session->getBasketId(),
            $session->getSalesChannelId(),
        );
    }

    public function createSalesChannelContext(
        string $token,
        string $salesChannelId,
    ): SalesChannelContext {
        return $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters($salesChannelId, $token)
        );
    }

    public function createSalesChannelContextWithCustomer(
        string $token,
        string $salesChannelId,
        string $customerId,
        ?string $paymentMethodId = null,
        ?string $shippingMethodId = null,
    ): SalesChannelContext {
        $payload = [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
        ];

        if ($paymentMethodId !== null) {
            $payload[SalesChannelContextService::PAYMENT_METHOD_ID] = $paymentMethodId;
        }

        if ($shippingMethodId !== null) {
            $payload[SalesChannelContextService::SHIPPING_METHOD_ID] = $shippingMethodId;
        }

        $this->salesChannelContextPersister->save($token, $payload, $salesChannelId);

        return $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters($salesChannelId, $token)
        );
    }

    public function updateProductQuantities(
        Cart $cart,
        SalesChannelContext $context,
        array $quantityEvents,
    ): Cart {
        try {
            foreach ($quantityEvents as $event) {
                $cart = $this->updateSingleProductQuantity($cart, $context, $event);
            }

            $this->cartPersister->save($cart, $context);

            return $cart;
        } catch (CartException|InvalidBasketException $e) {
            throw new InvalidBasketException(sprintf('Failed to update product quantities: %s', $e->getMessage()));
        }
    }

    public function applyPromoCodes(
        Cart $cart,
        SalesChannelContext $context,
        array $promoCodes,
    ): ApplyPromoCodesResult {
        $errorMessages = [];

        foreach ($promoCodes as $promoEvent) {
            if ($promoEvent->isAdd() || $promoEvent->isUpdate()) {
                $cart = $this->addPromoCode($cart, $context, $promoEvent->code);

                $error = $this->extractPromotionError($cart);
                if ($error !== null) {
                    $errorMessages[] = $error;
                }
            } elseif ($promoEvent->isRemove()) {
                $cart = $this->removePromoCode($cart, $context, $promoEvent->code);
            }
        }

        $this->cartPersister->save($cart, $context);

        $errorMessage = !empty($errorMessages) ? implode('; ', $errorMessages) : null;

        return new ApplyPromoCodesResult($cart, $errorMessage);
    }

    public function addRelatedProducts(
        Cart $cart,
        SalesChannelContext $context,
        array $relatedProducts,
    ): Cart {
        try {
            foreach ($relatedProducts as $productEvent) {
                $lineItem = new LineItem(
                    $productEvent->productId,
                    LineItem::PRODUCT_LINE_ITEM_TYPE,
                    $productEvent->productId,
                    $productEvent->quantity
                );

                if ($cart->getLineItems()->get($productEvent->productId)) {
                    continue;
                }

                $cart = $this->cartService->add($cart, $lineItem, $context);
            }

            $this->cartPersister->save($cart, $context);

            return $cart;
        } catch (CartException|InvalidBasketException $e) {
            throw new InvalidBasketException(sprintf('Failed to add related products: %s', $e->getMessage()));
        }
    }

    public function removeAllPromoCodes(
        Cart $cart,
        SalesChannelContext $context,
    ): Cart {
        $promoLineItems = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);

        foreach ($promoLineItems as $lineItem) {
            $cart = $this->cartService->remove($cart, $lineItem->getId(), $context);
        }

        return $cart;
    }

    private function extractPromotionError(Cart $cart): ?string
    {
        foreach ($cart->getErrors() as $error) {
            if ($error instanceof PromotionNotFoundError
                || $error instanceof PromotionNotEligibleError
                || $error instanceof PromotionExcludedError
            ) {
                return $error->getMessage();
            }
        }

        return null;
    }

    /**
     * Aligns an incoming InPost quantity to the product's purchase step.
     *
     * The InPost Pay app changes quantity by 1 and ignores the product's purchase step
     * (e.g. items sold in pairs, step = 2). Applying the raw value desynchronises the
     * cart from the app (app shows 5, Shopware needs 6). We translate a +/-1 change from
     * the app into +/- one full step relative to the current cart quantity, clamped to the
     * product's min/max. Returns 0 when the product should be removed.
     */
    private function snapQuantityToPurchaseSteps(LineItem $lineItem, int $requestedQuantity): int
    {
        if ($requestedQuantity <= 0) {
            return 0;
        }

        $info = $lineItem->getQuantityInformation();
        $step = max(1, $info?->getPurchaseSteps() ?? 1);
        $min = max(1, $info?->getMinPurchase() ?? 1);
        $max = $info?->getMaxPurchase();

        if ($step === 1) {
            $target = $requestedQuantity;
        } else {
            $current = $lineItem->getQuantity();

            if ($requestedQuantity > $current) {
                $target = $current + $step;
            } elseif ($requestedQuantity < $current) {
                $target = $current - $step;
            } else {
                $target = $current;
            }
        }

        if ($target < $min) {
            $target = $min;
        }

        if ($max !== null && $target > $max) {
            $target = $max;
        }

        return $target;
    }

    private function updateSingleProductQuantity(
        Cart $cart,
        SalesChannelContext $context,
        QuantityEventData $event,
    ): Cart {
        $existingLineItem = $cart->getLineItems()->get($event->productId);

        if ($existingLineItem) {
            $targetQuantity = $this->snapQuantityToPurchaseSteps($existingLineItem, $event->quantity);

            if ($targetQuantity > 0) {
                $cart = $this->cartService->changeQuantity(
                    cart: $cart,
                    identifier: $existingLineItem->getId(),
                    quantity: $targetQuantity,
                    context: $context
                );
            } else {
                $cart = $this->cartService->remove($cart, $existingLineItem->getId(), $context);
            }
        } elseif ($event->quantity > 0) {
            $lineItem = new LineItem(
                $event->productId,
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                $event->productId,
                $event->quantity
            );

            $cart = $this->cartService->add($cart, $lineItem, $context);
        }

        return $cart;
    }

    private function addPromoCode(
        Cart $cart,
        SalesChannelContext $context,
        string $code,
    ): Cart {
        $existingPromos = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $existingPromo = $existingPromos->first();

        if ($existingPromo && $existingPromo->getReferencedId() === $code) {
            return $cart;
        }

        $lineItemId = Uuid::randomHex();
        $lineItem = new LineItem(
            $lineItemId,
            LineItem::PROMOTION_LINE_ITEM_TYPE,
            null,
            1
        );
        $lineItem->setRemovable(true);
        $lineItem->setReferencedId($code);

        try {
            $cart = $this->cartService->add($cart, $lineItem, $context);
        } catch (Throwable $e) {
            throw $e;
        }

        return $cart;
    }

    private function removePromoCode(
        Cart $cart,
        SalesChannelContext $context,
        string $code,
    ): Cart {
        $promoLineItems = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);

        foreach ($promoLineItems as $lineItem) {
            if ($lineItem->getReferencedId() === $code) {
                $cart = $this->cartService->remove($cart, $lineItem->getId(), $context);
                break;
            }
        }

        return $cart;
    }
}
