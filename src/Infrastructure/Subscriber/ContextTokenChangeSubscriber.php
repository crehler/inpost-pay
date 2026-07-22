<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Subscriber;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\Event\{SalesChannelContextRestoredEvent, SalesChannelContextTokenChangeEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

final readonly class ContextTokenChangeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private InpostBasketSessionService $basketSessionService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Rejestruje mapowanie zdarzeń kontekstu sprzedażowego na metody obsługi.
     *
     * @return array<string,string> tablica, gdzie klucze to nazwy klas zdarzeń, a wartości to odpowiadające im nazwy metod obsługi
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextTokenChangeEvent::class => 'onContextTokenChange',
            SalesChannelContextRestoredEvent::class => 'onContextRestored',
        ];
    }

    /**
     * Migruje sesję koszyka InPost po zmianie tokenu kontekstu sprzedaży.
     *
     * Jeśli poprzedni i bieżący token różnią się, próbuje przenieść sesję koszyka z poprzedniego tokenu na nowy
     * oraz zapisuje informację o powodzeniu lub błędzie w logach.
     *
     * @param SalesChannelContextTokenChangeEvent $event zdarzenie zawierające poprzedni i bieżący token kontekstu sprzedaży
     */
    public function onContextTokenChange(SalesChannelContextTokenChangeEvent $event): void
    {
        $previousToken = $event->getPreviousToken();
        $currentToken = $event->getCurrentToken();

        if ($previousToken === $currentToken) {
            return;
        }

        try {
            $this->basketSessionService->migrateSession($previousToken, $currentToken);

            $this->logger->info('InPost basket session migrated after token change', [
                'old_token' => $previousToken,
                'new_token' => $currentToken,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to migrate InPost basket session', [
                'old_token' => $previousToken,
                'new_token' => $currentToken,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Migruje sesję koszyka InPost po przywróceniu kontekstu sprzedażowego, jeśli token kontekstu uległ zmianie.
     *
     * To obsługuje scenariusz, w którym koszyk gościa jest scalany z kontem klienta podczas logowania i zmiana tokena
     * nie jest zgłaszana przez SalesChannelContextTokenChangeEvent, więc sesja InPost musi zostać przeniesiona tutaj.
     *
     * @param SalesChannelContextRestoredEvent $event zdarzenie zawierające bieżący oraz przywrócony kontekst sprzedażowy
     */
    public function onContextRestored(SalesChannelContextRestoredEvent $event): void
    {
        $previousToken = $event->getCurrentSalesChannelContext()->getToken();
        $currentToken = $event->getRestoredSalesChannelContext()->getToken();

        if ($previousToken === $currentToken) {
            return;
        }

        try {
            $this->basketSessionService->migrateSession($previousToken, $currentToken);

            $this->logger->info('InPost basket session migrated after context restore', [
                'old_token' => $previousToken,
                'new_token' => $currentToken,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to migrate InPost basket session after context restore', [
                'old_token' => $previousToken,
                'new_token' => $currentToken,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
