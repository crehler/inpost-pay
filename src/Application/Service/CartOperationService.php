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
use Crehler\InpostPay\Application\Dto\EventData\{PromoCodeEventData, QuantityEventData};
use Crehler\InpostPay\Domain\Entity\PromoCode;
use Crehler\InpostPay\Domain\Exception\{BasketSessionNotFoundException, InvalidBasketException};
use Crehler\InpostPay\Infrastructure\Persistence\Entity\InpostBasketSessionEntity;
use Psr\Log\LoggerInterface;
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
use function mb_strtolower;
use function sprintf;

readonly class CartOperationService
{
    public function __construct(
        private InpostBasketSessionService $basketSessionService,
        private SalesChannelContextServiceInterface $salesChannelContextService,
        private SalesChannelContextPersister $salesChannelContextPersister,
        private AbstractCartPersister $cartPersister,
        private ShopwareCartService $cartService,
        private LoggerInterface $logger,
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

        $incoming = [];
        $hasExplicitAction = false;
        foreach ($promoCodes as $promoEvent) {
            $incoming[] = ['action' => $promoEvent->action, 'code' => $promoEvent->code];
            $hasExplicitAction = $hasExplicitAction || $promoEvent->action !== null;
        }

        $this->logger->info('Promo code event received', [
            'events' => $incoming,
            'codesInCart' => $this->cartPromoCodes($cart),
        ]);

        $cart = $hasExplicitAction
            ? $this->applyPromoCodeActions($cart, $context, $promoCodes, $errorMessages)
            : $this->syncPromoCodes($cart, $context, $promoCodes, $errorMessages);

        $this->cartPersister->save($cart, $context);

        $errorMessage = !empty($errorMessages) ? implode('; ', $errorMessages) : null;

        $this->logger->info('Promo code event processed', [
            'codesInCart' => $this->cartPromoCodes($cart),
            'errorMessage' => $errorMessage,
        ]);

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
        $wanted = $this->foldCode($code);

        foreach ($promoLineItems as $lineItem) {
            $lineItemCode = $lineItem->getReferencedId();

            if ($lineItemCode !== null && $this->foldCode($lineItemCode) === $wanted) {
                $this->logger->info('Removing promo code line item', [
                    'code' => $code,
                    'lineItemId' => $lineItem->getId(),
                ]);

                return $this->cartService->remove($cart, $lineItem->getId(), $context);
            }
        }

        // The full code list separates "the app sent something else" from "the code is
        // already gone".
        $this->logger->warning('Promo code to remove not found in cart', [
            'code' => $code,
            'codesInCart' => $this->cartPromoCodes($cart),
        ]);

        return $cart;
    }

    /**
     * The InPost Pay app sends no per-code action: a PROMO_CODES event lists the codes the
     * basket is supposed to end up with, so a removal reaches us as "here is what is left".
     * We therefore diff that list against the cart instead of adding every entry in it -
     * without this, removing a code in the app did nothing at all. BasketService already
     * treats an empty list as "drop them all", which is this same contract with nothing left.
     *
     * @param PromoCodeEventData[] $promoCodes
     * @param string[]             $errorMessages
     */
    private function syncPromoCodes(
        Cart $cart,
        SalesChannelContext $context,
        array $promoCodes,
        array &$errorMessages,
    ): Cart {
        // Keyed by folded code so the comparisons below ignore letter case; the value keeps
        // the spelling the shopper used, which is what Shopware gets to see.
        $desired = [];
        foreach ($promoCodes as $promoEvent) {
            if ($promoEvent->code === '') {
                continue;
            }

            $desired[$this->foldCode($promoEvent->code)] = $promoEvent->code;
        }

        // Removals first, so the additions below work against the recalculated cart.
        foreach ($this->cartPromoCodes($cart) as $code) {
            if (isset($desired[$this->foldCode($code)])) {
                continue;
            }

            $cart = $this->removePromoCode($cart, $context, $code);
        }

        $present = [];
        foreach ($this->cartPromoCodes($cart) as $code) {
            $present[$this->foldCode($code)] = true;
        }

        foreach ($desired as $folded => $code) {
            if (isset($present[$folded])) {
                continue;
            }

            // The app renders our synthetic Omnibus entry as an ordinary chip and echoes it
            // back as if the shopper had typed it. No Shopware promotion stands behind it,
            // so adding it would only yield "Promotion with code OMNIBUS not found!". It
            // stays in $desired above on purpose: a merchant promotion that really is coded
            // OMNIBUS then still matches the cart and survives the removal pass.
            if ($folded === $this->foldCode(PromoCode::OMNIBUS)) {
                continue;
            }

            $cart = $this->addPromoCodeCollectingErrors($cart, $context, $code, $errorMessages);
        }

        return $cart;
    }

    /**
     * Path for payloads that do carry an explicit action. Nothing we have seen from InPost
     * uses it, but honouring it keeps a REMOVE from being misread as "the basket should end
     * up holding exactly this one code".
     *
     * @param PromoCodeEventData[] $promoCodes
     * @param string[]             $errorMessages
     */
    private function applyPromoCodeActions(
        Cart $cart,
        SalesChannelContext $context,
        array $promoCodes,
        array &$errorMessages,
    ): Cart {
        foreach ($promoCodes as $promoEvent) {
            $cart = match ($promoEvent->action) {
                PromoCodeEventData::ACTION_ADD,
                PromoCodeEventData::ACTION_UPDATE => $this->addPromoCodeCollectingErrors($cart, $context, $promoEvent->code, $errorMessages),
                PromoCodeEventData::ACTION_REMOVE => $this->removePromoCode($cart, $context, $promoEvent->code),
                default => $this->skipUnsupportedAction($cart, $promoEvent),
            };
        }

        return $cart;
    }

    /**
     * @param string[] $errorMessages
     */
    private function addPromoCodeCollectingErrors(
        Cart $cart,
        SalesChannelContext $context,
        string $code,
        array &$errorMessages,
    ): Cart {
        $cart = $this->addPromoCode($cart, $context, $code);

        $error = $this->extractPromotionError($cart);
        if ($error !== null) {
            $errorMessages[] = $error;
        }

        return $cart;
    }

    private function skipUnsupportedAction(Cart $cart, PromoCodeEventData $promoEvent): Cart
    {
        $this->logger->warning('Promo code event with unsupported action - ignored', [
            'action' => $promoEvent->action,
            'code' => $promoEvent->code,
        ]);

        return $cart;
    }

    /**
     * Shopware resolves a promotion code regardless of letter case and the app forwards
     * whatever the shopper typed - the demo log holds "rabat25", "Rabat25" and "RABAT25"
     * for one and the same promotion. Comparing raw strings would make those look like
     * different codes and churn the promotion through a needless remove/add.
     */
    private function foldCode(string $code): string
    {
        return mb_strtolower($code);
    }

    /**
     * Codes of the promotions the shopper applied by code; automatic promotions carry none,
     * so they are left out and the diff never touches them. Returned as a list rather than
     * as array keys, because PHP would turn a numeric code like "2024" into an int.
     *
     * @return string[]
     */
    private function cartPromoCodes(Cart $cart): array
    {
        $codes = [];

        foreach ($cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE) as $lineItem) {
            $code = $lineItem->getReferencedId();
            if ($code === null || $code === '') {
                continue;
            }

            $codes[] = $code;
        }

        return $codes;
    }
}
