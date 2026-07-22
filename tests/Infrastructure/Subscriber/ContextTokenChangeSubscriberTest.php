<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Infrastructure\Subscriber;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Infrastructure\Subscriber\ContextTokenChangeSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class ContextTokenChangeSubscriberTest extends TestCase
{
    public function testOnContextTokenChangeMigratesSession(): void
    {
        $event = $this->createMock(SalesChannelContextTokenChangeEvent::class);
        $event->method('getPreviousToken')->willReturn('guest-token');
        $event->method('getCurrentToken')->willReturn('new-token');

        $sessionService = $this->createMock(InpostBasketSessionService::class);
        $sessionService->expects($this->once())
            ->method('migrateSession')
            ->with('guest-token', 'new-token');

        $subscriber = new ContextTokenChangeSubscriber(
            $sessionService,
            $this->createMock(LoggerInterface::class),
        );

        $subscriber->onContextTokenChange($event);
    }

    public function testOnContextRestoredMigratesSessionUsingGuestAndRestoredTokens(): void
    {
        $guestContext = $this->createMock(SalesChannelContext::class);
        $guestContext->method('getToken')->willReturn('guest-token');

        $restoredContext = $this->createMock(SalesChannelContext::class);
        $restoredContext->method('getToken')->willReturn('customer-token');

        $event = $this->createMock(SalesChannelContextRestoredEvent::class);
        $event->method('getCurrentSalesChannelContext')->willReturn($guestContext);
        $event->method('getRestoredSalesChannelContext')->willReturn($restoredContext);

        $sessionService = $this->createMock(InpostBasketSessionService::class);
        $sessionService->expects($this->once())
            ->method('migrateSession')
            ->with('guest-token', 'customer-token');

        $subscriber = new ContextTokenChangeSubscriber(
            $sessionService,
            $this->createMock(LoggerInterface::class),
        );

        $subscriber->onContextRestored($event);
    }

    public function testOnContextRestoredSkipsWhenTokensAreEqual(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getToken')->willReturn('same-token');

        $event = $this->createMock(SalesChannelContextRestoredEvent::class);
        $event->method('getCurrentSalesChannelContext')->willReturn($context);
        $event->method('getRestoredSalesChannelContext')->willReturn($context);

        $sessionService = $this->createMock(InpostBasketSessionService::class);
        $sessionService->expects($this->never())->method('migrateSession');

        $subscriber = new ContextTokenChangeSubscriber(
            $sessionService,
            $this->createMock(LoggerInterface::class),
        );

        $subscriber->onContextRestored($event);
    }
}
