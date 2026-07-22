<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Persistence\Repository;

use Crehler\InpostPay\Infrastructure\Checkout\InpostPayCodPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Throwable;

readonly class CodTransactionOrderResolver
{
    public function __construct(
        #[Target('order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
    ) {
    }

    /**
     * Returns the order id for a transaction handled by the COD payment method, or
     * null for any other handler (online payments must not trigger an update push).
     */
    public function resolveOrderId(string $transactionId, Context $context): ?string
    {
        try {
            $criteria = new Criteria([$transactionId]);
            $criteria->addAssociation('paymentMethod');
            $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

            if ($transaction?->getPaymentMethod()?->getHandlerIdentifier() !== InpostPayCodPaymentHandler::class) {
                return null;
            }

            return $transaction->getOrderId();
        } catch (Throwable) {
            return null;
        }
    }
}
