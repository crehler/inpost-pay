<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Service;

use Crehler\InpostPay\Domain\ValueObject\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

use function sprintf;

final readonly class OrderStatusDescriptionMapper
{
    /**
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        OrderTransactionStates::STATE_OPEN => 'Oczekujące na płatność',
        OrderTransactionStates::STATE_IN_PROGRESS => 'W trakcie realizacji',
        OrderTransactionStates::STATE_PAID => 'Opłacone',
        OrderTransactionStates::STATE_PARTIALLY_PAID => 'Częściowo opłacone',
        OrderTransactionStates::STATE_REFUNDED => 'Zwrócone',
        OrderTransactionStates::STATE_PARTIALLY_REFUNDED => 'Częściowo zwrócone',
        OrderTransactionStates::STATE_CANCELLED => 'Anulowane',
        OrderTransactionStates::STATE_FAILED => 'Nieudane',
        OrderTransactionStates::STATE_REMINDED => 'Przypomnienie',
        OrderTransactionStates::STATE_AUTHORIZED => 'Autoryzowane',
        OrderTransactionStates::STATE_CHARGEBACK => 'Chargeback',
        OrderTransactionStates::STATE_UNCONFIRMED => 'Niepotwierdzone',
    ];

    public function mapToPolish(string $shopwareState): string
    {
        return self::STATUS_MAP[$shopwareState] ?? 'Nieznany status';
    }

    public function mapPaymentStatusToPolish(PaymentStatus $paymentStatus): string
    {
        return match ($paymentStatus) {
            PaymentStatus::UNPAID => 'Nieopłacone',
            PaymentStatus::STARTED => 'Rozpoczęto płatność',
            PaymentStatus::PENDING => 'Oczekuje na płatność',
            PaymentStatus::AUTHORIZED => 'Autoryzowane',
            PaymentStatus::COMPLETED => 'Opłacone',
            PaymentStatus::COD => 'Płatność przy odbiorze',
            PaymentStatus::DECLINED => 'Odmowa płatności',
            PaymentStatus::ERROR => 'Błąd płatności',
            PaymentStatus::FAILED => 'Nieudana płatność',
            PaymentStatus::CANCELLED => 'Anulowane',
            PaymentStatus::REFUNDED => 'Zwrócone',
        };
    }

    public function mapDeliveryStateToPolish(
        string $deliveryState,
        int $shippedCount = 0,
        int $totalCount = 1,
    ): string {
        return match ($deliveryState) {
            OrderDeliveryStates::STATE_OPEN => 'Oczekuje na wysyłkę',
            OrderDeliveryStates::STATE_SHIPPED => $totalCount > 1
                ? sprintf('Wysłano %d/%d', $totalCount, $totalCount)
                : 'Wysłane',
            OrderDeliveryStates::STATE_PARTIALLY_SHIPPED => sprintf('Wysłano %d/%d', $shippedCount, $totalCount),
            OrderDeliveryStates::STATE_RETURNED => 'Zwrócone',
            OrderDeliveryStates::STATE_PARTIALLY_RETURNED => 'Częściowo zwrócone',
            OrderDeliveryStates::STATE_CANCELLED => 'Anulowane',
            default => 'Nieznany status wysyłki',
        };
    }
}
