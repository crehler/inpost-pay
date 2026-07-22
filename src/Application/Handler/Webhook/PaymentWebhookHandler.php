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
use Crehler\InpostPay\Domain\Exception\OrderNotFoundException;
use Crehler\InpostPay\Domain\ValueObject\{WebhookEventType, WebhookResult};
use Crehler\InpostPay\Infrastructure\Persistence\Repository\ShopwareOrderRepository;
use DateTimeImmutable;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\{AutoconfigureTag, Target};
use Throwable;

use function explode;
use function str_contains;

#[AutoconfigureTag('inpost_pay.webhook_handler')]
final readonly class PaymentWebhookHandler implements WebhookHandlerInterface
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
        return $eventType->isPaymentEvent();
    }

    public function handle(WebhookPayloadDto $dto): WebhookResult
    {
        $eventData = $dto->eventData;
        $orderReference = $eventData['orderReference'] ?? null;

        if (!$orderReference) {
            return WebhookResult::failed('Missing orderReference in webhook payload');
        }

        $orderId = $this->extractOrderId($orderReference);

        try {
            $context = Context::createDefaultContext();
            $order = $this->orderRepository->findOrderById($orderId, $context);

            if (!$order) {
                throw OrderNotFoundException::withId($orderId);
            }

            $transaction = $order->getTransactions()?->first();
            if (!$transaction) {
                throw new RuntimeException('Order has no transaction');
            }

            $transactionId = $transaction->getId();

            $this->transitionPaymentState(
                $transactionId,
                $dto->eventType,
                $context,
            );

            $this->savePaymentMetadata($orderId, $eventData, $context);

            return WebhookResult::acknowledged('Payment webhook processed', [
                'orderReference' => $orderId,
            ]);
        } catch (OrderNotFoundException $e) {
            return WebhookResult::failed($e->getMessage());
        } catch (IllegalTransitionException $e) {
            return WebhookResult::acknowledged('Payment webhook received, state already transitioned', [
                'orderReference' => $orderId,
            ]);
        } catch (Throwable $e) {
            return WebhookResult::failed('Failed to process payment webhook: ' . $e->getMessage());
        }
    }

    private function transitionPaymentState(
        string $transactionId,
        WebhookEventType $eventType,
        Context $context,
    ): void {
        match ($eventType) {
            WebhookEventType::PAYMENT_AUTHORIZED => $this->transactionStateHandler->paid($transactionId, $context),
            WebhookEventType::PAYMENT_DECLINED => $this->transactionStateHandler->fail($transactionId, $context),
            default => null,
        };
    }

    private function savePaymentMetadata(string $orderId, array $eventData, Context $context): void
    {
        $this->orderEntityRepository->update([
            [
                'id' => $orderId,
                'customFields' => [
                    'inpost_pay_webhook_payment_id' => $eventData['payment']['id'] ?? null,
                    'inpost_pay_webhook_payment_reference' => $eventData['payment']['reference'] ?? null,
                    'inpost_pay_webhook_payment_method' => $eventData['payment']['method'] ?? null,
                    'inpost_pay_webhook_status' => $eventData['status'] ?? null,
                    'inpost_pay_webhook_processed_at' => (new DateTimeImmutable())->format('c'),
                ],
            ],
        ], $context);
    }

    private function extractOrderId(string $orderReference): string
    {
        if (str_contains($orderReference, '|')) {
            return explode('|', $orderReference, 2)[0];
        }

        return $orderReference;
    }
}
