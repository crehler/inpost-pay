<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Application\Service;

use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Application\Service\OrderDataExtractor;
use Crehler\InpostPay\Application\Service\OrderUpdateNotifier;
use Crehler\InpostPay\Domain\Service\OrderStatusDescriptionMapper;
use Crehler\InpostPay\Infrastructure\Persistence\Repository\ShopwareOrderRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class OrderUpdateNotifierTest extends TestCase
{
    public function testSkipsWhenOrderNotFound(): void
    {
        $repository = $this->createMock(ShopwareOrderRepository::class);
        $repository->method('findOrderById')->willReturn(null);

        $facade = $this->createMock(InpostPayFacadeInterface::class);
        $facade->expects($this->never())->method('pushOrderUpdate');

        $notifier = $this->notifier($repository, $facade);
        $notifier->notify('missing-order', null, $this->createMock(Context::class));
    }

    public function testSkipsWhenOrderIsNotInpostPay(): void
    {
        $order = $this->createMock(OrderEntity::class);
        $order->method('getCustomFields')->willReturn([]);

        $repository = $this->createMock(ShopwareOrderRepository::class);
        $repository->method('findOrderById')->willReturn($order);

        $facade = $this->createMock(InpostPayFacadeInterface::class);
        $facade->expects($this->never())->method('pushOrderUpdate');

        $notifier = $this->notifier($repository, $facade);
        $notifier->notify('order-uuid', 'ORDER_REJECTED', $this->createMock(Context::class));
    }

    private function notifier(
        ShopwareOrderRepository $repository,
        InpostPayFacadeInterface $facade,
    ): OrderUpdateNotifier {
        return new OrderUpdateNotifier(
            $repository,
            $this->createMock(OrderDataExtractor::class),
            new OrderStatusDescriptionMapper(),
            $facade,
            $this->createMock(LoggerInterface::class),
        );
    }
}
