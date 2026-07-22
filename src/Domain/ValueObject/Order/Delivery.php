<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Order;

use Crehler\InpostPay\Domain\ValueObject\{Address, DeliveryType, PhoneNumber};

readonly class Delivery
{
    public function __construct(
        public DeliveryType $deliveryType,
        public ?PhoneNumber $phoneNumber = null,
        public ?Address $deliveryAddress = null,
        public ?string $deliveryPoint = null,
        public ?array $deliveryCodes = null,
        public ?string $digitalDeliveryEmail = null,
        public ?string $mail = null,
        public ?string $courierNote = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'delivery_type' => $this->deliveryType->value,
        ];

        if ($this->phoneNumber) {
            $data['phone_number'] = $this->phoneNumber->toArray();
        }
        if ($this->deliveryAddress) {
            $data['delivery_address'] = $this->deliveryAddress->toArray();
        }
        if ($this->deliveryPoint) {
            $data['delivery_point'] = $this->deliveryPoint;
        }
        if ($this->deliveryCodes) {
            $data['delivery_codes'] = $this->deliveryCodes;
        }
        if ($this->digitalDeliveryEmail) {
            $data['digital_delivery_email'] = $this->digitalDeliveryEmail;
        }
        if ($this->mail) {
            $data['mail'] = $this->mail;
        }
        if ($this->courierNote) {
            $data['courier_note'] = $this->courierNote;
        }

        return $data;
    }
}
