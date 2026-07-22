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
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

final readonly class CurrencyChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private const SUPPORTED_CURRENCY = 'PLN';

    public function __construct(
        private InpostBasketSessionService $basketSessionService,
        private InpostPayFacadeInterface $inpostPayFacade,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextSwitchEvent::class => 'onContextSwitch',
        ];
    }

    public function onContextSwitch(SalesChannelContextSwitchEvent $event): void
    {
        $requestData = $event->getRequestDataBag();
        $context = $event->getSalesChannelContext();

        $newCurrencyId = $requestData->get('currencyId');
        if ($newCurrencyId === null) {
            return;
        }

        $currentCurrency = $context->getCurrency()->getIsoCode();

        if ($currentCurrency !== self::SUPPORTED_CURRENCY) {
            return;
        }

        $cartToken = $context->getToken();
        $session = $this->basketSessionService->getSessionByBasketId($cartToken);

        if ($session === null) {
            return;
        }

        try {
            $this->inpostPayFacade->desynchronizeBasket($cartToken);

            $this->logger->info('InPost basket desynchronized due to currency change', [
                'cart_token' => $cartToken,
                'from_currency' => $currentCurrency,
                'to_currency_id' => $newCurrencyId,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to desynchronize InPost basket on currency change', [
                'cart_token' => $cartToken,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
