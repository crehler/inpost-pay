<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\Service\OrderStatusDescriptionMapper;
use Crehler\InpostPay\Domain\ValueObject\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use function str_replace;

/**
 * Resolves the human-readable status description sent to the InPost Pay app.
 *
 * Every label is configurable in the plugin config (InpostPay.config.statusLabel*),
 * defaulting to the hard-coded values from {@see OrderStatusDescriptionMapper}.
 * When a config value is empty the mapper default is used, so the shop owner only
 * overrides what they want.
 */
final readonly class StatusLabelResolver
{
    private const PREFIX = 'InpostPay.config.';

    /** @var array<string, string> transaction state => config key */
    private const TRANSACTION = [
        OrderTransactionStates::STATE_OPEN => 'statusLabelTxOpen',
        OrderTransactionStates::STATE_IN_PROGRESS => 'statusLabelTxInProgress',
        OrderTransactionStates::STATE_PAID => 'statusLabelTxPaid',
        OrderTransactionStates::STATE_PARTIALLY_PAID => 'statusLabelTxPartiallyPaid',
        OrderTransactionStates::STATE_REFUNDED => 'statusLabelTxRefunded',
        OrderTransactionStates::STATE_PARTIALLY_REFUNDED => 'statusLabelTxPartiallyRefunded',
        OrderTransactionStates::STATE_CANCELLED => 'statusLabelTxCancelled',
        OrderTransactionStates::STATE_FAILED => 'statusLabelTxFailed',
        OrderTransactionStates::STATE_REMINDED => 'statusLabelTxReminded',
        OrderTransactionStates::STATE_AUTHORIZED => 'statusLabelTxAuthorized',
        OrderTransactionStates::STATE_CHARGEBACK => 'statusLabelTxChargeback',
        OrderTransactionStates::STATE_UNCONFIRMED => 'statusLabelTxUnconfirmed',
    ];

    /** @var array<string, string> PaymentStatus value => config key */
    private const PAYMENT = [
        'UNPAID' => 'statusLabelPayUnpaid',
        'STARTED' => 'statusLabelPayStarted',
        'PENDING' => 'statusLabelPayPending',
        'AUTHORIZED' => 'statusLabelPayAuthorized',
        'COMPLETED' => 'statusLabelPayCompleted',
        'COD' => 'statusLabelPayCod',
        'DECLINED' => 'statusLabelPayDeclined',
        'ERROR' => 'statusLabelPayError',
        'FAILED' => 'statusLabelPayFailed',
        'CANCELLED' => 'statusLabelPayCancelled',
        'REFUNDED' => 'statusLabelPayRefunded',
    ];

    /** @var array<string, string> delivery state => config key */
    private const DELIVERY = [
        OrderDeliveryStates::STATE_OPEN => 'statusLabelDelOpen',
        OrderDeliveryStates::STATE_RETURNED => 'statusLabelDelReturned',
        OrderDeliveryStates::STATE_PARTIALLY_RETURNED => 'statusLabelDelPartiallyReturned',
        OrderDeliveryStates::STATE_CANCELLED => 'statusLabelDelCancelled',
    ];

    public function __construct(
        private SystemConfigService $systemConfigService,
        private OrderStatusDescriptionMapper $mapper,
    ) {
    }

    public function transactionLabel(string $state, ?string $salesChannelId = null): string
    {
        $override = $this->override(self::TRANSACTION[$state] ?? 'statusLabelTxUnknown', $salesChannelId);

        return $override ?? $this->mapper->mapToPolish($state);
    }

    public function paymentLabel(PaymentStatus $status, ?string $salesChannelId = null): string
    {
        $override = $this->override(self::PAYMENT[$status->value] ?? null, $salesChannelId);

        return $override ?? $this->mapper->mapPaymentStatusToPolish($status);
    }

    public function deliveryLabel(
        string $state,
        int $shippedCount = 0,
        int $totalCount = 1,
        ?string $salesChannelId = null,
    ): string {
        // Shipped/partially shipped carry counts and use placeholder patterns.
        if ($state === OrderDeliveryStates::STATE_SHIPPED) {
            $key = $totalCount > 1 ? 'statusLabelDelShippedMulti' : 'statusLabelDelShipped';
            $override = $this->override($key, $salesChannelId);
            if ($override !== null) {
                return $this->fillCounts($override, $totalCount, $totalCount);
            }

            // Fully shipped: pass total/total so the rendered count is consistent
            // with the override branch above (the mapper renders total/total anyway).
            return $this->mapper->mapDeliveryStateToPolish($state, $totalCount, $totalCount);
        }

        if ($state === OrderDeliveryStates::STATE_PARTIALLY_SHIPPED) {
            $override = $this->override('statusLabelDelPartiallyShipped', $salesChannelId);
            if ($override !== null) {
                return $this->fillCounts($override, $shippedCount, $totalCount);
            }

            return $this->mapper->mapDeliveryStateToPolish($state, $shippedCount, $totalCount);
        }

        $override = $this->override(self::DELIVERY[$state] ?? 'statusLabelDelUnknown', $salesChannelId);
        if ($override !== null) {
            return $override;
        }

        return $this->mapper->mapDeliveryStateToPolish($state, $shippedCount, $totalCount);
    }

    private function override(?string $configKey, ?string $salesChannelId): ?string
    {
        if ($configKey === null) {
            return null;
        }

        $value = $this->systemConfigService->getString(self::PREFIX . $configKey, $salesChannelId);

        return $value !== '' ? $value : null;
    }

    private function fillCounts(string $pattern, int $shipped, int $total): string
    {
        return str_replace(['%shipped%', '%total%'], [(string) $shipped, (string) $total], $pattern);
    }
}
