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
use Crehler\InpostPay\Infrastructure\Api\InpostController;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\{AfterLineItemAddedEvent, AfterLineItemQuantityChangedEvent, AfterLineItemRemovedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

final readonly class CheckoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private InpostBasketSessionService $sessionService,
        private InpostController $inpostController,
        private InpostPayFacadeInterface $inpostPayFacade,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterLineItemAddedEvent::class => 'onCartChanged',
            AfterLineItemRemovedEvent::class => 'onCartChanged',
            AfterLineItemQuantityChangedEvent::class => 'onCartChanged',
        ];
    }

    public function onCartChanged(
        AfterLineItemAddedEvent|AfterLineItemRemovedEvent|AfterLineItemQuantityChangedEvent $event,
    ): void {
        $cart = $event->getCart();
        $context = $event->getSalesChannelContext();

        if ($context->hasState(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE)) {
            return;
        }

        try {
            $basketId = $cart->getToken();

            $session = $this->sessionService->getSessionByBasketId($basketId);
            if (!$session) {
                return;
            }

            if ($cart->getLineItems()->count() === 0) {
                $this->logger->info('Cart is empty, desynchronizing InPost Pay basket', [
                    'basket_id' => $basketId,
                ]);
                $this->inpostPayFacade->desynchronizeBasket($basketId);

                return;
            }

            $this->logger->info('Cart changed, updating InPost Pay basket', [
                'basket_id' => $basketId,
            ]);

            $this->inpostController->updateBasket($basketId);
        } catch (Throwable $e) {
            $this->logger->error('Failed to update InPost Pay basket after cart change', [
                'basket_id' => $cart->getToken(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
