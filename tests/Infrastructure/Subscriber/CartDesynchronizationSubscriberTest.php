<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Infrastructure\Subscriber;

use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Infrastructure\Persistence\Entity\InpostBasketSessionEntity;
use Crehler\InpostPay\Infrastructure\Subscriber\CartDesynchronizationSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class CartDesynchronizationSubscriberTest extends TestCase
{
    public function testOnCartDeletedDesynchronizesBasket(): void
    {
        $cartToken = 'cart-token-fixture';

        $context = $this->createMock(Context::class);
        $context->method('hasState')
            ->with(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE)
            ->willReturn(false);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getToken')->willReturn($cartToken);

        $event = $this->createMock(CartDeletedEvent::class);
        $event->method('getSalesChannelContext')->willReturn($salesChannelContext);

        $session = $this->createMock(InpostBasketSessionEntity::class);

        $sessionService = $this->createMock(InpostBasketSessionService::class);
        $sessionService->method('getSessionByBasketId')->with($cartToken)->willReturn($session);

        $facade = $this->createMock(InpostPayFacadeInterface::class);
        $facade->expects($this->once())
            ->method('desynchronizeBasket')
            ->with($cartToken);
        $facade->expects($this->never())->method('desynchronizeBasketLocally');

        $subscriber = new CartDesynchronizationSubscriber(
            $sessionService,
            $facade,
            $this->createMock(LoggerInterface::class),
        );

        $subscriber->onCartDeleted($event);
    }

    public function testOnCartDeletedShortCircuitsWhenInpostInternalStateActive(): void
    {
        $context = $this->createMock(Context::class);
        $context->method('hasState')
            ->with(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE)
            ->willReturn(true);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $event = $this->createMock(CartDeletedEvent::class);
        $event->method('getSalesChannelContext')->willReturn($salesChannelContext);

        $sessionService = $this->createMock(InpostBasketSessionService::class);
        $sessionService->expects($this->never())->method('getSessionByBasketId');

        $facade = $this->createMock(InpostPayFacadeInterface::class);
        $facade->expects($this->never())->method('desynchronizeBasket');
        $facade->expects($this->never())->method('desynchronizeBasketLocally');

        $subscriber = new CartDesynchronizationSubscriber(
            $sessionService,
            $facade,
            $this->createMock(LoggerInterface::class),
        );

        $subscriber->onCartDeleted($event);
    }

    public function testOnCartDeletedDoesNothingWhenNoSession(): void
    {
        $cartToken = 'cart-token-fixture';

        $context = $this->createMock(Context::class);
        $context->method('hasState')->willReturn(false);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getToken')->willReturn($cartToken);

        $event = $this->createMock(CartDeletedEvent::class);
        $event->method('getSalesChannelContext')->willReturn($salesChannelContext);

        $sessionService = $this->createMock(InpostBasketSessionService::class);
        $sessionService->method('getSessionByBasketId')->with($cartToken)->willReturn(null);

        $facade = $this->createMock(InpostPayFacadeInterface::class);
        $facade->expects($this->never())->method('desynchronizeBasket');
        $facade->expects($this->never())->method('desynchronizeBasketLocally');

        $subscriber = new CartDesynchronizationSubscriber(
            $sessionService,
            $facade,
            $this->createMock(LoggerInterface::class),
        );

        $subscriber->onCartDeleted($event);
    }
}
