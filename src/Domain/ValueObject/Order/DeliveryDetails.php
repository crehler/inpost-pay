<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Order;

use Crehler\InpostPay\Domain\ValueObject\{Address, DeliveryType, Money, PhoneNumber};
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

readonly class DeliveryDetails
{
    public function __construct(
        public DeliveryType $type,
        public PhoneNumber $phone,
        public Money $deliveryPrice,
        public array $deliveryCodes = [],
        public ?string $deliveryPoint = null,
        public ?Address $deliveryAddress = null,
        public ?string $digitalDeliveryEmail = null,
        public ?string $mail = null,
        public ?string $courierNote = null,
        public ?DateTimeInterface $deliveryDate = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'delivery_type' => $this->type->value,
            'delivery_price' => $this->deliveryPrice->toArray(),
            'delivery_date' => $this->formatDeliveryDate(),
        ];

        if ($this->phone) {
            $data['phone_number'] = $this->phone->toArray();
        }
        if (!empty($this->deliveryCodes)) {
            $data['delivery_codes'] = $this->deliveryCodes;
        }
        if ($this->deliveryPoint) {
            $data['delivery_point'] = $this->deliveryPoint;
        }
        if ($this->deliveryAddress) {
            $data['delivery_address'] = $this->deliveryAddress->toArray();
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

    private function formatDeliveryDate(): string
    {
        $date = $this->deliveryDate ?? new DateTimeImmutable('+1 day');
        $utc = $date instanceof DateTimeImmutable
            ? $date->setTimezone(new DateTimeZone('UTC'))
            : (new DateTimeImmutable())->setTimestamp($date->getTimestamp())->setTimezone(new DateTimeZone('UTC'));

        return $utc->format('Y-m-d\TH:i:s.000\Z');
    }
}
