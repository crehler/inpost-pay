<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

enum WebhookEventType: string
{
    case PAYMENT_AUTHORIZED = 'PAYMENT_AUTHORIZED';
    case PAYMENT_DECLINED = 'PAYMENT_DECLINED';
    case REFUND = 'REFUND';
    case REFUND_DECLINED = 'REFUND_DECLINED';
    case SETTLEMENT = 'SETTLEMENT';

    public function getSignatureFields(): array
    {
        return match ($this) {
            self::PAYMENT_AUTHORIZED, self::PAYMENT_DECLINED => [
                'eventData.amount.currency',
                'eventData.amount.valueInCents',
                'eventData.createdDate',
                'eventData.eventDateTime',
                'eventData.merchantId',
                'eventData.orderReference',
                'eventData.payment.id',
                'eventData.payment.method',
                'eventData.payment.reference',
                'eventData.status',
                'eventType',
            ],
            self::REFUND, self::REFUND_DECLINED => [
                'eventData.amount.currency',
                'eventData.amount.valueInCents',
                'eventData.createdDate',
                'eventData.eventDateTime',
                'eventData.merchantId',
                'eventData.operationId',
                'eventData.payment.id',
                'eventData.payment.method',
                'eventData.refundReference',
                'eventData.status',
                'eventType',
            ],
            self::SETTLEMENT => [
                'eventData.amount.currency',
                'eventData.amount.valueInCents',
                'eventData.createdDate',
                'eventData.eventDateTime',
                'eventData.merchantId',
                'eventData.settlementId',
                'eventData.transferReference',
                'eventType',
            ],
        };
    }

    public function isPaymentEvent(): bool
    {
        return match ($this) {
            self::PAYMENT_AUTHORIZED, self::PAYMENT_DECLINED => true,
            default => false,
        };
    }

    public function isRefundEvent(): bool
    {
        return match ($this) {
            self::REFUND, self::REFUND_DECLINED => true,
            default => false,
        };
    }

    public function isSettlementEvent(): bool
    {
        return $this === self::SETTLEMENT;
    }
}
