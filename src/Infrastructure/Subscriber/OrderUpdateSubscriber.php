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
use Crehler\InpostPay\Application\MessageQueue\SendOrderUpdateMessage;
use Crehler\InpostPay\Domain\ValueObject\OrderEventStatus;
use Crehler\InpostPay\Infrastructure\Persistence\Repository\CodTransactionOrderResolver;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\{OrderDeliveryDefinition, OrderDeliveryStates};
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\{OrderDefinition, OrderStates};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final readonly class OrderUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private EntityRepository $orderDeliveryRepository,
        private CodTransactionOrderResolver $codTransactionOrderResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onStateTransition',
            OrderDeliveryDefinition::ENTITY_NAME . '.written' => 'onDeliveryWritten',
        ];
    }

    public function onStateTransition(StateMachineTransitionEvent $event): void
    {
        if ($event->getContext()->hasState(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE)) {
            return;
        }

        $toState = $event->getToPlace()->getTechnicalName();

        switch ($event->getEntityName()) {
            case OrderDefinition::ENTITY_NAME:
                $this->dispatch($event->getEntityId(), $this->mapOrderStatus($toState));

                return;

            case OrderDeliveryDefinition::ENTITY_NAME:
                if ($toState !== OrderDeliveryStates::STATE_SHIPPED
                    && $toState !== OrderDeliveryStates::STATE_PARTIALLY_SHIPPED
                ) {
                    return;
                }

                $orderId = $this->resolveOrderId($event->getEntityId(), $event->getContext());
                if ($orderId !== null) {
                    $this->dispatch($orderId, null);
                }

                return;

            case OrderTransactionDefinition::ENTITY_NAME:
                // COD payment is settled by the merchant/ERP, never by the incoming
                // payment webhook, so pushing on it cannot echo-loop. Lets the app
                // reflect the COD "paid" state (online payments stay ignored below).
                $orderId = $this->codTransactionOrderResolver->resolveOrderId($event->getEntityId(), $event->getContext());
                if ($orderId !== null) {
                    $this->dispatch($orderId, null);
                }

                return;

            default:
                // Ignore anything else, and online order_transaction transitions:
                // prevents echo loops from the incoming payment webhook.
                return;
        }
    }

    public function onDeliveryWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->hasState(InpostPayFacadeInterface::INPOST_PAY_UPDATE_STATE)) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            if (empty($payload['trackingCodes'])) {
                continue;
            }

            $deliveryId = $payload['id'] ?? null;
            if ($deliveryId === null) {
                continue;
            }

            $orderId = $this->resolveOrderId($deliveryId, $event->getContext());
            if ($orderId !== null) {
                $this->dispatch($orderId, null);
            }
        }
    }

    private function mapOrderStatus(string $toState): ?string
    {
        return match ($toState) {
            OrderStates::STATE_CANCELLED => OrderEventStatus::REJECTED->value,
            OrderStates::STATE_COMPLETED => OrderEventStatus::COMPLETED->value,
            default => null,
        };
    }

    private function resolveOrderId(string $deliveryId, Context $context): ?string
    {
        try {
            $delivery = $this->orderDeliveryRepository->search(new Criteria([$deliveryId]), $context)->first();

            return $delivery?->getOrderId();
        } catch (Throwable) {
            return null;
        }
    }

    private function dispatch(string $orderId, ?string $orderStatus): void
    {
        $this->messageBus->dispatch(new SendOrderUpdateMessage($orderId, $orderStatus));
    }
}
