<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use Crehler\InpostPay\Domain\ValueObject\{ServiceCode, TimeOfWeekRange};
use DateTimeInterface;
use JsonSerializable;

readonly class ServiceOptions implements JsonSerializable
{
    public function __construct(
        public ServiceCode $serviceCode,
        public bool $enabled = false,
        public ?float $additionalCostGross = null,
        public ?TimeOfWeekRange $availabilityRange = null,
    ) {
    }

    public function isAvailable(DateTimeInterface $now): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (!$this->serviceCode->isAvailabilityTimeDependent()) {
            return true;
        }

        if ($this->availabilityRange === null) {
            return true;
        }

        return $this->availabilityRange->contains($now);
    }

    public function hasAdditionalCost(): bool
    {
        return $this->additionalCostGross !== null && $this->additionalCostGross > 0;
    }

    public function jsonSerialize(): array
    {
        return [
            'serviceCode' => $this->serviceCode->value,
            'enabled' => $this->enabled,
            'additionalCostGross' => $this->additionalCostGross,
            'availabilityRange' => $this->availabilityRange?->jsonSerialize(),
        ];
    }
}
