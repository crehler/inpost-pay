<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Domain\Service;

use Crehler\InpostPay\Domain\Service\OrderStatusDescriptionMapper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;

final class OrderStatusDescriptionMapperTest extends TestCase
{
    public function testMapDeliveryStateToPolish_open_returnsWaiting(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Oczekuje na wysyłkę',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_OPEN),
        );
    }

    public function testMapDeliveryStateToPolish_shippedSingleDelivery_returnsShipped(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Wysłane',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_SHIPPED, 1, 1),
        );
    }

    public function testMapDeliveryStateToPolish_shippedMultipleDeliveries_returnsXY(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Wysłano 2/2',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_SHIPPED, 2, 2),
        );
    }

    public function testMapDeliveryStateToPolish_partiallyShipped_returnsXY(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Wysłano 1/2',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_PARTIALLY_SHIPPED, 1, 2),
        );
    }

    public function testMapDeliveryStateToPolish_partiallyShipped3of5_returnsXY(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Wysłano 3/5',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_PARTIALLY_SHIPPED, 3, 5),
        );
    }

    public function testMapDeliveryStateToPolish_cancelled_returnsCancelled(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Anulowane',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_CANCELLED),
        );
    }

    public function testMapDeliveryStateToPolish_returned_returnsReturned(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Zwrócone',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_RETURNED),
        );
    }

    public function testMapDeliveryStateToPolish_partiallyReturned_returnsPartiallyReturned(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Częściowo zwrócone',
            $mapper->mapDeliveryStateToPolish(OrderDeliveryStates::STATE_PARTIALLY_RETURNED),
        );
    }

    public function testMapDeliveryStateToPolish_unknownState_returnsUnknown(): void
    {
        $mapper = new OrderStatusDescriptionMapper();

        self::assertSame(
            'Nieznany status wysyłki',
            $mapper->mapDeliveryStateToPolish('some_random_unknown_state'),
        );
    }
}
