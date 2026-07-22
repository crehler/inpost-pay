<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Subscriber;

use Crehler\InpostPay\Domain\Event\{BasketConfirmedEvent, BasketDesynchronizedEvent, BasketRejectedEvent};
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function count;

readonly class BasketConfirmationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BasketConfirmedEvent::class => [
                ['onBasketConfirmed', 100],
            ],
            BasketRejectedEvent::class => [
                ['onBasketRejected', 100],
            ],
            BasketDesynchronizedEvent::class => [
                ['onBasketDesynchronized', 100],
            ],
        ];
    }

    public function onBasketConfirmed(BasketConfirmedEvent $event): void
    {
        try {
            $this->logger->info('Basket confirmed via InPost Pay', [
                'basket_id' => $event->basketId,
                'inpost_basket_id' => $event->inpostBasketId,
                'customer_phone' => $event->phoneNumber->getFullNumber(),
                'confirmed_at' => $event->confirmedAt->format('c'),
                'delivery_methods_count' => count($event->deliveryOptions),
                'products_count' => count($event->products),
                'basket_total' => $event->basketSummary->getEffectivePrice()->getGross(),
                'currency' => $event->basketSummary->currency,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error handling basket confirmed event', [
                'basket_id' => $event->basketId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    public function onBasketRejected(BasketRejectedEvent $event): void
    {
        try {
            $this->logger->warning('Basket rejected by user in InPost Pay', [
                'basket_id' => $event->basketId,
                'inpost_basket_id' => $event->inpostBasketId,
                'customer_phone' => $event->phoneNumber->getFullNumber(),
                'rejected_at' => $event->rejectedAt->format('c'),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error handling basket rejected event', [
                'basket_id' => $event->basketId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    public function onBasketDesynchronized(BasketDesynchronizedEvent $event): void
    {
        try {
            $this->logger->info('Basket desynchronized from InPost Pay', [
                'basket_id' => $event->basketId,
                'sales_channel_id' => $event->salesChannelId,
                'desynchronized_at' => $event->desynchronizedAt->format('c'),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error handling basket desynchronized event', [
                'basket_id' => $event->basketId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
