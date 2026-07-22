<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Application\Dto\EventData;

use Crehler\InpostPay\Application\Dto\EventData\OrderUpdateEventData;
use Crehler\InpostPay\Domain\ValueObject\OrderEventStatus;
use PHPUnit\Framework\TestCase;

final class OrderUpdateEventDataTest extends TestCase
{
    public function testToArrayOmitsAllOptionalFieldsWhenEmpty(): void
    {
        $eventData = new OrderUpdateEventData();

        self::assertSame([], $eventData->toArray());
    }

    public function testToArrayIncludesOnlyDescriptionWhenStatusAndTrackingMissing(): void
    {
        $eventData = new OrderUpdateEventData(
            orderMerchantStatusDescription: 'W trakcie realizacji',
        );

        self::assertSame(
            ['order_merchant_status_description' => 'W trakcie realizacji'],
            $eventData->toArray(),
        );
    }

    public function testToArrayOmitsEmptyDescriptionString(): void
    {
        $eventData = new OrderUpdateEventData(
            orderMerchantStatusDescription: '',
            deliveryReferencesList: ['12345678'],
        );

        self::assertSame(
            ['delivery_references_list' => ['12345678']],
            $eventData->toArray(),
        );
    }

    public function testToArraySerializesFullPayload(): void
    {
        $eventData = new OrderUpdateEventData(
            orderStatus: OrderEventStatus::REJECTED,
            orderMerchantStatusDescription: 'Anulowane',
            deliveryReferencesList: ['12345678', '87654321'],
        );

        self::assertSame(
            [
                'order_status' => 'ORDER_REJECTED',
                'order_merchant_status_description' => 'Anulowane',
                'delivery_references_list' => ['12345678', '87654321'],
            ],
            $eventData->toArray(),
        );
    }

    public function testCompletedStatusMapsToInpostValue(): void
    {
        $eventData = new OrderUpdateEventData(orderStatus: OrderEventStatus::COMPLETED);

        self::assertSame(['order_status' => 'ORDER_COMPLETED'], $eventData->toArray());
    }
}
