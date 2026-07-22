<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Subscriber;

use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\{CartDeletedEvent, CartMergedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

final class CartDesynchronizationSubscriber implements EventSubscriberInterface
{
    private bool $cartMergeInProgress = false;

    public function __construct(
        private readonly InpostBasketSessionService $basketSessionService,
        private readonly InpostPayFacadeInterface $inpostPayFacade,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartMergedEvent::class => 'onCartMerged',
            CartDeletedEvent::class => 'onCartDeleted',
        ];
    }

    public function onCartMerged(CartMergedEvent $event): void
    {
        // During guest -> customer login Shopware merges the guest cart into the customer
        // cart and then DELETES the guest cart (CartRestorer::enrichCustomerContext),
        // firing CartDeletedEvent. That deletion is plumbing, not a user removing the cart,
        // so it must NOT unbind the InPost basket - the binding follows the new cart token
        // via ContextTokenChangeSubscriber::onContextRestored. CartMergedEvent fires right
        // before the guest-cart deletion, so flag it and let onCartDeleted skip the desync.
        $this->cartMergeInProgress = true;
    }

    public function onCartDeleted(CartDeletedEvent $event): void
    {
        if ($this->cartMergeInProgress) {
            $this->cartMergeInProgress = false;
            $this->logger->info('Skipping InPost desync: cart deletion is part of a login cart merge');

            return;
        }

        $context = $event->getSalesChannelContext();

        // Prevent recursion during InPost internal operations
        if ($context->getContext()->hasState(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE)) {
            return;
        }

        // Get basket ID from context token
        $cartToken = $context->getToken();

        $session = $this->basketSessionService->getSessionByBasketId($cartToken);

        if ($session === null) {
            return;
        }

        try {
            $this->inpostPayFacade->desynchronizeBasket($cartToken);

            $this->logger->info('InPost basket desynchronized due to cart deletion', [
                'cart_token' => $cartToken,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to desynchronize InPost basket', [
                'cart_token' => $cartToken,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
