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
use Crehler\InpostPay\Application\MessageQueue\SendOrderUpdateMessage;
use Crehler\InpostPay\Infrastructure\Subscriber\OrderUpdateSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class OrderUpdateSubscriberTest extends TestCase
{
    public function testSubscribesToStateTransitionAndDeliveryWritten(): void
    {
        $events = OrderUpdateSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(StateMachineTransitionEvent::class, $events);
        self::assertArrayHasKey('order_delivery.written', $events);
    }

    public function testIgnoresOrderTransactionTransition(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = new OrderUpdateSubscriber($bus, $this->createMock(EntityRepository::class));
        $subscriber->onStateTransition(
            $this->transitionEvent('order_transaction', 'tx-id', 'paid', $this->context()),
        );
    }

    public function testSkipsWhenInternalStateActive(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = new OrderUpdateSubscriber($bus, $this->createMock(EntityRepository::class));
        $subscriber->onStateTransition(
            $this->transitionEvent(OrderDefinition::ENTITY_NAME, 'order-id', OrderStates::STATE_CANCELLED, $this->context(true)),
        );
    }

    public function testOrderCancelledDispatchesRejected(): void
    {
        $bus = $this->expectDispatch('order-uuid', 'ORDER_REJECTED');

        $subscriber = new OrderUpdateSubscriber($bus, $this->createMock(EntityRepository::class));
        $subscriber->onStateTransition(
            $this->transitionEvent(OrderDefinition::ENTITY_NAME, 'order-uuid', OrderStates::STATE_CANCELLED, $this->context()),
        );
    }

    public function testOrderCompletedDispatchesCompleted(): void
    {
        $bus = $this->expectDispatch('order-uuid', 'ORDER_COMPLETED');

        $subscriber = new OrderUpdateSubscriber($bus, $this->createMock(EntityRepository::class));
        $subscriber->onStateTransition(
            $this->transitionEvent(OrderDefinition::ENTITY_NAME, 'order-uuid', OrderStates::STATE_COMPLETED, $this->context()),
        );
    }

    public function testOrderInProgressDispatchesWithoutStatus(): void
    {
        $bus = $this->expectDispatch('order-uuid', null);

        $subscriber = new OrderUpdateSubscriber($bus, $this->createMock(EntityRepository::class));
        $subscriber->onStateTransition(
            $this->transitionEvent(OrderDefinition::ENTITY_NAME, 'order-uuid', OrderStates::STATE_IN_PROGRESS, $this->context()),
        );
    }

    public function testDeliveryShippedResolvesOrderAndDispatches(): void
    {
        $bus = $this->expectDispatch('order-uuid', null);
        $repo = $this->repositoryReturningOrderId('order-uuid');

        $subscriber = new OrderUpdateSubscriber($bus, $repo);
        $subscriber->onStateTransition(
            $this->transitionEvent(OrderDeliveryDefinition::ENTITY_NAME, 'delivery-id', OrderDeliveryStates::STATE_SHIPPED, $this->context()),
        );
    }

    public function testDeliveryOpenStateIgnored(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = new OrderUpdateSubscriber($bus, $this->createMock(EntityRepository::class));
        $subscriber->onStateTransition(
            $this->transitionEvent(OrderDeliveryDefinition::ENTITY_NAME, 'delivery-id', OrderDeliveryStates::STATE_OPEN, $this->context()),
        );
    }

    public function testDeliveryWrittenWithTrackingDispatches(): void
    {
        $bus = $this->expectDispatch('order-uuid', null);
        $repo = $this->repositoryReturningOrderId('order-uuid');

        $writeResult = $this->createMock(EntityWriteResult::class);
        $writeResult->method('getPayload')->willReturn(['id' => 'delivery-id', 'trackingCodes' => ['12345678']]);

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->method('getContext')->willReturn($this->context());
        $event->method('getWriteResults')->willReturn([$writeResult]);

        $subscriber = new OrderUpdateSubscriber($bus, $repo);
        $subscriber->onDeliveryWritten($event);
    }

    public function testDeliveryWrittenWithoutTrackingIgnored(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $writeResult = $this->createMock(EntityWriteResult::class);
        $writeResult->method('getPayload')->willReturn(['id' => 'delivery-id', 'stateId' => 'some-state']);

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->method('getContext')->willReturn($this->context());
        $event->method('getWriteResults')->willReturn([$writeResult]);

        $subscriber = new OrderUpdateSubscriber($bus, $this->createMock(EntityRepository::class));
        $subscriber->onDeliveryWritten($event);
    }

    private function context(bool $internalState = false): Context
    {
        $context = $this->createMock(Context::class);
        $context->method('hasState')
            ->with(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE)
            ->willReturn($internalState);

        return $context;
    }

    private function transitionEvent(
        string $entityName,
        string $entityId,
        string $toState,
        Context $context,
    ): StateMachineTransitionEvent {
        $toPlace = $this->createMock(StateMachineStateEntity::class);
        $toPlace->method('getTechnicalName')->willReturn($toState);

        $event = $this->createMock(StateMachineTransitionEvent::class);
        $event->method('getEntityName')->willReturn($entityName);
        $event->method('getEntityId')->willReturn($entityId);
        $event->method('getToPlace')->willReturn($toPlace);
        $event->method('getContext')->willReturn($context);

        return $event;
    }

    private function expectDispatch(string $orderId, ?string $orderStatus): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn ($message): bool => $message instanceof SendOrderUpdateMessage
                    && $message->orderId === $orderId
                    && $message->orderStatus === $orderStatus,
            ))
            ->willReturn(new Envelope(new SendOrderUpdateMessage($orderId, $orderStatus)));

        return $bus;
    }

    private function repositoryReturningOrderId(?string $orderId): EntityRepository
    {
        $delivery = null;
        if ($orderId !== null) {
            $delivery = $this->createMock(OrderDeliveryEntity::class);
            $delivery->method('getOrderId')->willReturn($orderId);
        }

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($delivery);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        return $repo;
    }
}
