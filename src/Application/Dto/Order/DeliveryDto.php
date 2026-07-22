<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto\Order;

use Crehler\InpostPay\Domain\Exception\InvalidBasketException;
use Crehler\InpostPay\Domain\ValueObject\{Address, AddressDetails, DeliveryType, Money, PhoneNumber};
use Crehler\InpostPay\Domain\ValueObject\Order\DeliveryDetails;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;
use ValueError;

use function sprintf;

readonly class DeliveryDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Delivery type is required')]
        public DeliveryType $deliveryType,

        #[Assert\NotNull(message: 'Phone number is required')]
        public PhoneNumber $phoneNumber,

        #[Assert\NotNull(message: 'Delivery price is required')]
        public Money $deliveryPrice,

        #[Assert\Type(type: 'string', message: 'Delivery mail must be a string')]
        public ?string $mail = null,

        public ?array $deliveryCodes = null,

        #[Assert\Type(type: 'string', message: 'Delivery point must be a string')]
        public ?string $deliveryPoint = null,

        public ?Address $deliveryAddress = null,

        #[Assert\Email(message: 'Digital delivery email must be valid')]
        public ?string $digitalDeliveryEmail = null,

        #[Assert\Type(type: 'string', message: 'Courier note must be a string')]
        public ?string $courierNote = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $deliveryType = DeliveryType::from($data['delivery_type'] ?? '');

            $phoneNumber = new PhoneNumber(
                countryPrefix: (string) ($data['phone_number']['country_prefix'] ?? ''),
                phone: (string) ($data['phone_number']['phone'] ?? '')
            );

            $deliveryAddress = null;
            if (!empty($data['delivery_address'])) {
                $addrData = $data['delivery_address'];
                $addressDetails = null;
                if (!empty($addrData['address_details'])) {
                    $details = $addrData['address_details'];
                    $addressDetails = new AddressDetails(
                        street: isset($details['street']) ? (string) $details['street'] : null,
                        building: isset($details['building']) ? (string) $details['building'] : null,
                        flat: isset($details['flat']) ? (string) $details['flat'] : null,
                    );
                }

                $deliveryAddress = new Address(
                    countryCode: (string) ($addrData['country_code'] ?? 'PL'),
                    city: (string) ($addrData['city'] ?? ''),
                    postalCode: (string) ($addrData['postal_code'] ?? ''),
                    streetLine: (string) ($addrData['address'] ?? ''),
                    details: $addressDetails,
                    name: isset($addrData['name']) ? (string) $addrData['name'] : null,
                );
            }

            $deliveryPrice = Money::zero();
            if (!empty($data['delivery_price'])) {
                $deliveryPrice = Money::fromFloat(
                    net: (float) ($data['delivery_price']['net'] ?? 0),
                    gross: (float) ($data['delivery_price']['gross'] ?? 0),
                    vat: (float) ($data['delivery_price']['vat'] ?? 0),
                );
            }

            return new self(
                deliveryType: $deliveryType,
                phoneNumber: $phoneNumber,
                deliveryPrice: $deliveryPrice,
                mail: isset($data['mail']) ? (string) $data['mail'] : null,
                deliveryCodes: $data['delivery_codes'] ?? null,
                deliveryPoint: isset($data['delivery_point']) ? (string) $data['delivery_point'] : null,
                deliveryAddress: $deliveryAddress,
                digitalDeliveryEmail: isset($data['digital_delivery_email']) ? (string) $data['digital_delivery_email'] : null,
                courierNote: isset($data['courier_note']) ? (string) $data['courier_note'] : null,
            );
        } catch (ValueError $e) {
            throw new InvalidBasketException(sprintf('Invalid delivery type: %s', $e->getMessage()));
        } catch (InvalidArgumentException $e) {
            throw new InvalidBasketException(sprintf('Invalid delivery data: %s', $e->getMessage()));
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Invalid delivery data: %s', $e->getMessage()));
        }
    }

    public function toDomain(): DeliveryDetails
    {
        return new DeliveryDetails(
            type: $this->deliveryType,
            phone: $this->phoneNumber,
            deliveryPrice: $this->deliveryPrice,
            deliveryCodes: $this->deliveryCodes ?? [],
            deliveryPoint: $this->deliveryPoint,
            deliveryAddress: $this->deliveryAddress,
            digitalDeliveryEmail: $this->digitalDeliveryEmail,
            mail: $this->mail,
            courierNote: $this->courierNote,
        );
    }

    public function toArray(): array
    {
        $data = [
            'delivery_type' => $this->deliveryType->value,
            'phone_number' => $this->phoneNumber->toArray(),
            'delivery_price' => $this->deliveryPrice->toArray(),
        ];

        if ($this->mail) {
            $data['mail'] = $this->mail;
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

        if ($this->courierNote) {
            $data['courier_note'] = $this->courierNote;
        }

        return $data;
    }
}
