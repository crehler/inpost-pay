<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto\EventData;

use Crehler\InpostPay\Domain\ValueObject\OrderEventStatus;

final readonly class OrderUpdateEventData
{
    /**
     * @param string[] $deliveryReferencesList
     */
    public function __construct(
        public ?OrderEventStatus $orderStatus = null,
        public ?string $orderMerchantStatusDescription = null,
        public array $deliveryReferencesList = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $orderStatus = isset($data['order_status'])
            ? OrderEventStatus::from((string) $data['order_status'])
            : null;

        return new self(
            orderStatus: $orderStatus,
            orderMerchantStatusDescription: $data['order_merchant_status_description'] ?? null,
            deliveryReferencesList: $data['delivery_references_list'] ?? [],
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->orderStatus !== null) {
            $data['order_status'] = $this->orderStatus->value;
        }

        if ($this->orderMerchantStatusDescription !== null && $this->orderMerchantStatusDescription !== '') {
            $data['order_merchant_status_description'] = $this->orderMerchantStatusDescription;
        }

        if ($this->deliveryReferencesList !== []) {
            $data['delivery_references_list'] = $this->deliveryReferencesList;
        }

        return $data;
    }
}
