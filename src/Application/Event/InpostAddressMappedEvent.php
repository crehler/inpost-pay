<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Event;

use Crehler\InpostPay\Domain\ValueObject\Address;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after an InPost address is mapped to Shopware address fields,
 * before the address is persisted or compared against existing ones. Listeners
 * may replace the mapped data via setAddressData() — e.g. move the building
 * number from street to additionalAddressLine1; the same data drives address
 * deduplication, so changes stay consistent between creation and matching.
 */
class InpostAddressMappedEvent extends Event
{
    /**
     * @param array<string, mixed> $addressData
     */
    public function __construct(
        private readonly Address $inpostAddress,
        private array $addressData,
    ) {
    }

    public function getInpostAddress(): Address
    {
        return $this->inpostAddress;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAddressData(): array
    {
        return $this->addressData;
    }

    /**
     * @param array<string, mixed> $addressData
     */
    public function setAddressData(array $addressData): void
    {
        $this->addressData = $addressData;
    }
}
