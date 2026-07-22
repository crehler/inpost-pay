<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Persistence\Repository;

use Crehler\InpostPay\Domain\ValueObject\PaymentType;
use Crehler\InpostPay\Infrastructure\Checkout\{InpostPayCodPaymentHandler, InpostPayPaymentHandler};
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

readonly class InpostPayPaymentMethodResolver
{
    public function __construct(
        private EntityRepository $paymentMethodRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function getPaymentMethodId(Context $context, ?PaymentType $paymentType = null): string
    {
        if ($paymentType === PaymentType::CASH_ON_DELIVERY) {
            $codMethodId = $this->findActiveMethodId(InpostPayCodPaymentHandler::class, $context);

            if ($codMethodId !== null) {
                return $codMethodId;
            }

            // Do not silently fall back to the online method for a COD order - that hides a wrong payment on the order.
            $this->logger->error('InPost Pay COD payment method requested but not found or inactive', [
                'handler' => InpostPayCodPaymentHandler::class,
            ]);

            throw new RuntimeException('InPost Pay COD payment method requested but not found or inactive. Ensure the "InPost Pay - pobranie" payment method exists and is active.');
        }

        $id = $this->findActiveMethodId(InpostPayPaymentHandler::class, $context);

        if ($id === null) {
            throw new RuntimeException('InPost Pay payment method not found or inactive. Please ensure the InpostPay plugin is properly installed and activated.');
        }

        return $id;
    }

    public function getBasePaymentMethod(Context $context): ?PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', InpostPayPaymentHandler::class));
        $criteria->addFilter(new EqualsFilter('active', true));

        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->first();

        return $paymentMethod instanceof PaymentMethodEntity ? $paymentMethod : null;
    }

    private function findActiveMethodId(string $handlerIdentifier, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->paymentMethodRepository->searchIds($criteria, $context)->firstId();
    }
}
