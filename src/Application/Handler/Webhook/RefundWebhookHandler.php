<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Handler\Webhook;

use Crehler\InpostPay\Application\Dto\WebhookPayloadDto;
use Crehler\InpostPay\Domain\ValueObject\{WebhookEventType, WebhookResult};
use Crehler\InpostPay\Infrastructure\Persistence\Repository\ShopwareOrderRepository;
use DateTimeImmutable;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\{AutoconfigureTag, Target};
use Throwable;

#[AutoconfigureTag('inpost_pay.webhook_handler')]
final readonly class RefundWebhookHandler implements WebhookHandlerInterface
{
    public function __construct(
        private ShopwareOrderRepository $orderRepository,
        private OrderTransactionStateHandler $transactionStateHandler,
        #[Target('order.repository')]
        private EntityRepository $orderEntityRepository,
    ) {
    }

    public function supports(WebhookEventType $eventType): bool
    {
        return $eventType->isRefundEvent();
    }

    public function handle(WebhookPayloadDto $dto): WebhookResult
    {
        $eventData = $dto->eventData;
        $paymentId = $eventData['payment']['id'] ?? null;
        $operationId = $eventData['operationId'] ?? null;

        if (!$paymentId) {
            return WebhookResult::failed('Missing payment.id in webhook payload');
        }

        try {
            $context = Context::createDefaultContext();

            $order = $this->findOrderByPaymentId($paymentId, $context);

            if (!$order) {
                return WebhookResult::acknowledged('Refund webhook received, order not found', [
                    'payment_id' => $paymentId,
                ]);
            }

            $orderId = $order->getId();
            $transaction = $order->getTransactions()?->first();

            if (!$transaction) {
                throw new RuntimeException('Order has no transaction');
            }

            if ($dto->eventType === WebhookEventType::REFUND) {
                $this->transitionToRefundState($transaction->getId(), $context);
            }

            $this->saveRefundMetadata($orderId, $eventData, $dto->eventType, $context);

            return WebhookResult::acknowledged('Refund webhook processed', [
                'payment_id' => $paymentId,
                'operation_id' => $operationId,
            ]);
        } catch (IllegalTransitionException $e) {
            return WebhookResult::acknowledged('Refund webhook received, state already transitioned');
        } catch (Throwable $e) {
            return WebhookResult::failed('Failed to process refund webhook: ' . $e->getMessage());
        }
    }

    private function findOrderByPaymentId(string $paymentId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customFields.inpost_pay_webhook_payment_id', $paymentId)
        );
        $criteria->addAssociation('transactions.stateMachineState');

        return $this->orderEntityRepository->search($criteria, $context)->first();
    }

    private function transitionToRefundState(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->refund($transactionId, $context);
    }

    private function saveRefundMetadata(
        string $orderId,
        array $eventData,
        WebhookEventType $eventType,
        Context $context,
    ): void {
        $this->orderEntityRepository->update([
            [
                'id' => $orderId,
                'customFields' => [
                    'inpost_pay_refund_operation_id' => $eventData['operationId'] ?? null,
                    'inpost_pay_refund_reference' => $eventData['refundReference'] ?? null,
                    'inpost_pay_refund_status' => $eventData['status'] ?? null,
                    'inpost_pay_refund_event_type' => $eventType->value,
                    'inpost_pay_refund_processed_at' => (new DateTimeImmutable())->format('c'),
                ],
            ],
        ], $context);
    }
}
