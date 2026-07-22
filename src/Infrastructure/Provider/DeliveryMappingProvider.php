<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Provider;

use Crehler\InpostPay\Domain\ValueObject\DeliveryType;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use function array_column;
use function in_array;
use function is_array;
use function is_string;
use function reset;

readonly class DeliveryMappingProvider
{
    public function __construct(
        private SystemConfigService $systemConfig,
    ) {
    }

    public function getDeliveryTypeForShippingMethod(string $shippingMethodId, ?string $salesChannelId = null): ?DeliveryType
    {
        foreach (DeliveryType::cases() as $type) {
            if (in_array($shippingMethodId, $this->getMethodIdsByType($type, $salesChannelId), true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return string[] shipping method IDs mapped to the given delivery type, in admin-configured order
     */
    public function getMethodIdsByType(DeliveryType $deliveryType, ?string $salesChannelId = null): array
    {
        return $this->getMethodIds($this->configKeyForType($deliveryType), $salesChannelId);
    }

    public function getShippingMethodIdForDeliveryType(DeliveryType $deliveryType, ?string $salesChannelId = null): ?string
    {
        $methods = $this->getMethodIdsByType($deliveryType, $salesChannelId);

        return $methods[0] ?? null;
    }

    private function configKeyForType(DeliveryType $deliveryType): string
    {
        return match ($deliveryType) {
            DeliveryType::APM => 'InpostPay.config.apmShippingMethods',
            DeliveryType::COURIER => 'InpostPay.config.courierShippingMethods',
            DeliveryType::DIGITAL => 'InpostPay.config.digitalShippingMethods',
        };
    }

    private function getMethodIds(string $configKey, ?string $salesChannelId = null): array
    {
        $methods = $this->systemConfig->get($configKey, $salesChannelId);

        if ($methods === null || !is_array($methods)) {
            return [];
        }

        if (empty($methods)) {
            return [];
        }

        $firstElement = reset($methods);
        if (is_string($firstElement)) {
            return $methods;
        }

        return array_column($methods, 'id');
    }
}
