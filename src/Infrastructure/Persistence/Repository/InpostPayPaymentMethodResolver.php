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
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
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
        private InpostPayConfigProvider $configProvider,
        private LoggerInterface $logger,
    ) {
    }

    public function getPaymentMethodId(
        Context $context,
        ?PaymentType $paymentType = null,
        ?string $salesChannelId = null,
    ): string {
        if ($this->shouldUseCodMethod($paymentType, $salesChannelId)) {
            $codMethodId = $this->findActiveMethodId(InpostPayCodPaymentHandler::class, $context);

            if ($codMethodId !== null) {
                return $codMethodId;
            }

            $this->logger->warning('InPost Pay COD payment method enabled in config but not found or inactive, falling back to the default method.');
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

    private function shouldUseCodMethod(?PaymentType $paymentType, ?string $salesChannelId): bool
    {
        return $paymentType === PaymentType::CASH_ON_DELIVERY
            && $this->configProvider->isCodSeparatePaymentMethodEnabled($salesChannelId);
    }

    private function findActiveMethodId(string $handlerIdentifier, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->paymentMethodRepository->searchIds($criteria, $context)->firstId();
    }
}
