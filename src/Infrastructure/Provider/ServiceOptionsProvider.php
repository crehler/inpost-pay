<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Provider;

use Crehler\InpostPay\Domain\Entity\ServiceOptions;
use Crehler\InpostPay\Domain\ValueObject\{DeliveryType, ServiceCode, TimeOfWeekRange};
use Shopware\Core\System\SystemConfig\SystemConfigService;

readonly class ServiceOptionsProvider
{
    public function __construct(
        private SystemConfigService $systemConfig,
    ) {
    }

    public function getServiceOptions(ServiceCode $serviceCode, ?string $salesChannelId = null): ?ServiceOptions
    {
        $configKey = $this->getConfigKeyPrefix($serviceCode);

        $enabled = (bool) $this->systemConfig->get(
            "InpostPay.config.{$configKey}Enabled",
            $salesChannelId
        );

        if (!$enabled) {
            return null;
        }

        $additionalCost = (float) $this->systemConfig->get(
            "InpostPay.config.{$configKey}AdditionalCost",
            $salesChannelId
        );

        $availabilityRange = null;
        if ($serviceCode->isAvailabilityTimeDependent()) {
            $availabilityRange = $this->buildAvailabilityRange($configKey, $salesChannelId);
        }

        return new ServiceOptions(
            serviceCode: $serviceCode,
            enabled: true,
            additionalCostGross: $additionalCost > 0 ? $additionalCost : null,
            availabilityRange: $availabilityRange,
        );
    }

    /**
     * @return ServiceOptions[]
     */
    public function getAvailableServicesForDeliveryType(
        DeliveryType $deliveryType,
        ?string $salesChannelId = null,
    ): array {
        $services = [];

        foreach ($deliveryType->getAvailableServiceCodes() as $serviceCode) {
            $options = $this->getServiceOptions($serviceCode, $salesChannelId);
            if ($options !== null) {
                $services[] = $options;
            }
        }

        return $services;
    }

    private function getConfigKeyPrefix(ServiceCode $serviceCode): string
    {
        return match ($serviceCode) {
            ServiceCode::COD => 'codService',
            ServiceCode::PWW => 'pwwService',
        };
    }

    private function buildAvailabilityRange(string $configKey, ?string $salesChannelId): ?TimeOfWeekRange
    {
        $startDay = $this->systemConfig->get(
            "InpostPay.config.{$configKey}AvailabilityStartDay",
            $salesChannelId
        );
        $startTime = $this->systemConfig->get(
            "InpostPay.config.{$configKey}AvailabilityStartTime",
            $salesChannelId
        );
        $endDay = $this->systemConfig->get(
            "InpostPay.config.{$configKey}AvailabilityEndDay",
            $salesChannelId
        );
        $endTime = $this->systemConfig->get(
            "InpostPay.config.{$configKey}AvailabilityEndTime",
            $salesChannelId
        );

        if ($startDay === null || $endDay === null) {
            return null;
        }

        return TimeOfWeekRange::create(
            startDay: (int) $startDay,
            startTime: $startTime ?: '00:00',
            endDay: (int) $endDay,
            endTime: $endTime ?: '23:59'
        );
    }
}
