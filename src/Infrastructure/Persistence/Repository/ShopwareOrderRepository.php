<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Persistence\Repository;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

readonly class ShopwareOrderRepository
{
    public function __construct(
        private EntityRepository $orderRepository,
    ) {
    }

    public function findOrderById(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociations([
            'orderCustomer.customer',
            'orderCustomer.salutation',
            'billingAddress.country',
            'billingAddress.countryState',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingOrderAddress.countryState',
            'deliveries.stateMachineState',
            'lineItems.product',
            'lineItems.product.media',
            'lineItems.cover.media',
            'transactions.paymentMethod',
            'transactions.stateMachineState',
            'currency',
            'stateMachineState',
        ]);

        return $this->orderRepository->search($criteria, $context)->getEntities()->first();
    }
}
