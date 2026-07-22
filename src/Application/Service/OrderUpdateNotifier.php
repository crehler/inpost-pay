<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Application\Dto\EventData\OrderUpdateEventData;
use Crehler\InpostPay\Application\Dto\OrderUpdateNotificationDto;
use Crehler\InpostPay\Application\Facade\InpostPayFacadeInterface;
use Crehler\InpostPay\Domain\ValueObject\OrderEventStatus;
use Crehler\InpostPay\Infrastructure\Persistence\Repository\ShopwareOrderRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

readonly class OrderUpdateNotifier
{
    public function __construct(
        private ShopwareOrderRepository $shopwareOrderRepository,
        private OrderDataExtractor $orderDataExtractor,
        private StatusLabelResolver $statusResolver,
        private InpostPayFacadeInterface $facade,
        private LoggerInterface $logger,
    ) {
    }

    public function notify(string $orderId, ?string $orderStatusValue, Context $context): void
    {
        $order = $this->shopwareOrderRepository->findOrderById($orderId, $context);
        if ($order === null) {
            return;
        }

        // Only orders created via InPost Pay carry this custom field.
        if (($order->getCustomFields()['inpost_pay_basket_id'] ?? null) === null) {
            return;
        }

        $orderStatus = $orderStatusValue !== null
            ? OrderEventStatus::tryFrom($orderStatusValue)
            : null;

        $eventData = new OrderUpdateEventData(
            orderStatus: $orderStatus,
            orderMerchantStatusDescription: $this->buildStatusDescription($order),
            deliveryReferencesList: $this->orderDataExtractor->extractTrackingCodes($order),
        );

        $dto = new OrderUpdateNotificationDto(
            eventId: Uuid::randomHex(),
            eventDateTime: new DateTimeImmutable(),
            phoneNumber: $this->orderDataExtractor->getCustomerPhone($order),
            eventData: $eventData,
        );

        $this->facade->pushOrderUpdate($orderId, $dto);

        $this->logger->info('Pushed order update to InPost Pay', [
            'order_id' => $orderId,
            'order_status' => $orderStatus?->value,
        ]);
    }

    private function buildStatusDescription(OrderEntity $order): string
    {
        $deliveries = $order->getDeliveries();

        if ($deliveries !== null && $deliveries->count() > 0) {
            $total = $deliveries->count();
            $shipped = 0;
            foreach ($deliveries as $delivery) {
                $state = $delivery->getStateMachineState()?->getTechnicalName();
                if ($state === OrderDeliveryStates::STATE_SHIPPED
                    || $state === OrderDeliveryStates::STATE_PARTIALLY_SHIPPED
                ) {
                    ++$shipped;
                }
            }

            if ($shipped > 0) {
                $deliveryState = $shipped === $total
                    ? OrderDeliveryStates::STATE_SHIPPED
                    : OrderDeliveryStates::STATE_PARTIALLY_SHIPPED;

                return $this->statusResolver->deliveryLabel($deliveryState, $shipped, $total, $order->getSalesChannelId());
            }
        }

        $transactionState = $order->getTransactions()?->first()?->getStateMachineState()?->getTechnicalName();
        if ($transactionState !== null) {
            return $this->statusResolver->transactionLabel($transactionState, $order->getSalesChannelId());
        }

        return $this->statusResolver->transactionLabel(
            $order->getStateMachineState()?->getTechnicalName() ?? '',
            $order->getSalesChannelId()
        );
    }
}
