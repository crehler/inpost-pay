<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Logger;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use Psr\Log\LoggerInterface;

final readonly class ExtendedLogger
{
    public function __construct(
        private LoggerInterface $logger,
        private InpostPayConfigProvider $configProvider,
        private InpostBasketSessionService $basketSessionService,
    ) {
    }

    public function debug(string $message, array $context = [], ?string $salesChannelId = null): void
    {
        if (!$this->configProvider->isExtendedLoggingEnabled($salesChannelId)) {
            return;
        }

        $this->logger->debug($message, $context);
    }

    public function info(string $message, array $context = [], ?string $salesChannelId = null): void
    {
        if (!$this->configProvider->isExtendedLoggingEnabled($salesChannelId)) {
            return;
        }

        $this->logger->info($message, $context);
    }

    public function debugForBasket(string $basketId, string $message, array $context = []): void
    {
        $this->debug($message, $context, $this->resolveSalesChannelId($basketId));
    }

    public function infoForBasket(string $basketId, string $message, array $context = []): void
    {
        $this->info($message, $context, $this->resolveSalesChannelId($basketId));
    }

    private function resolveSalesChannelId(string $basketId): ?string
    {
        if ($this->configProvider->isExtendedLoggingEnabled(null)) {
            return null;
        }

        return $this->basketSessionService->getSessionByBasketId($basketId)?->getSalesChannelId();
    }
}
