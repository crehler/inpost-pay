<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Subscriber;

use Crehler\InpostPay\Infrastructure\Checkout\InpostPayPaymentHandler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\{Address, Email};

use function array_keys;
use function array_map;
use function filter_var;
use function is_a;
use function is_array;

final class OrderMailRecipientSubscriber implements EventSubscriberInterface
{
    private const INPOST_PAY_DELIVERY_MAIL = 'inpost_pay_delivery_mail';

    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeSentEvent::class => ['onMailBeforeSent', 0],
            FlowSendMailActionEvent::class => ['onFlowSendMailAction', 0],
        ];
    }

    public function onMailBeforeSent(MailBeforeSentEvent $event): void
    {
        $data = $event->getData();
        $context = $event->getContext();

        $orderId = $this->extractOrderId($data);

        if ($orderId === null) {
            return;
        }

        $deliveryEmail = $this->getInpostPayDeliveryEmail($orderId, $context);

        if ($deliveryEmail === null) {
            return;
        }

        $message = $event->getMessage();
        $originalRecipients = $this->getRecipientAddresses($message);

        $this->replaceRecipient($message, $deliveryEmail);

        $this->logger->info('InPost Pay: Email recipient overridden', [
            'order_id' => $orderId,
            'original_recipients' => $originalRecipients,
            'new_recipient' => $deliveryEmail,
        ]);
    }

    public function onFlowSendMailAction(FlowSendMailActionEvent $event): void
    {
        $flow = $event->getStorableFlow();

        if (!$flow->hasData(OrderAware::ORDER_ID)) {
            return;
        }

        $orderId = $flow->getData(OrderAware::ORDER_ID);

        if ($orderId === null) {
            return;
        }

        $deliveryEmail = $this->getInpostPayDeliveryEmail($orderId, $flow->getContext());

        if ($deliveryEmail === null) {
            return;
        }

        $dataBag = $event->getDataBag();
        $currentRecipients = $dataBag->get('recipients');

        if (!is_array($currentRecipients) || empty($currentRecipients)) {
            return;
        }

        $newRecipients = [];
        foreach ($currentRecipients as $email => $name) {
            $newRecipients[$deliveryEmail] = $name;
            break;
        }

        $dataBag->set('recipients', $newRecipients);

        $this->logger->info('InPost Pay: Flow mail recipient overridden', [
            'order_id' => $orderId,
            'original_recipients' => array_keys($currentRecipients),
            'new_recipient' => $deliveryEmail,
        ]);
    }

    private function getInpostPayDeliveryEmail(string $orderId, Context $context): ?string
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.paymentMethod');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order === null) {
            return null;
        }

        if (!$this->isInpostPayOrder($order)) {
            return null;
        }

        $customFields = $order->getCustomFields() ?? [];
        $deliveryEmail = $customFields[self::INPOST_PAY_DELIVERY_MAIL] ?? null;

        if ($deliveryEmail === null || !filter_var($deliveryEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $deliveryEmail;
    }

    private function isInpostPayOrder(OrderEntity $order): bool
    {
        $transactions = $order->getTransactions();

        if ($transactions === null || $transactions->count() === 0) {
            return false;
        }

        foreach ($transactions as $transaction) {
            $paymentMethod = $transaction->getPaymentMethod();

            if ($paymentMethod === null) {
                continue;
            }

            if (is_a($paymentMethod->getHandlerIdentifier(), InpostPayPaymentHandler::class, true)) {
                return true;
            }
        }

        return false;
    }

    private function extractOrderId(array $data): ?string
    {
        if (isset($data['order']['id'])) {
            return $data['order']['id'];
        }

        if (isset($data['orderId'])) {
            return $data['orderId'];
        }

        return null;
    }

    private function replaceRecipient(Email $message, string $newEmail): void
    {
        $currentTo = $message->getTo();

        if (empty($currentTo)) {
            return;
        }

        $firstName = $currentTo[0]->getName();

        $message->to(new Address($newEmail, $firstName));
    }

    private function getRecipientAddresses(Email $message): array
    {
        return array_map(
            fn (Address $address) => $address->getAddress(),
            $message->getTo()
        );
    }
}
