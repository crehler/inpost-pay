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
use Crehler\InpostPay\Domain\ValueObject\{Address, AddressDetails, PhoneNumber};
use Crehler\InpostPay\Domain\ValueObject\Order\CustomerInfo;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

use function sprintf;

readonly class AccountInfoDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Customer name is required')]
        #[Assert\Type(type: 'string', message: 'Customer name must be a string')]
        public string $name,

        #[Assert\NotBlank(message: 'Customer surname is required')]
        #[Assert\Type(type: 'string', message: 'Customer surname must be a string')]
        public string $surname,

        #[Assert\NotBlank(message: 'Customer email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public string $mail,

        #[Assert\NotNull(message: 'Phone number is required')]
        public PhoneNumber $phoneNumber,

        #[Assert\NotNull(message: 'Client address is required')]
        public Address $clientAddress,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            $phoneNumber = new PhoneNumber(
                countryPrefix: (string) ($data['phone_number']['country_prefix'] ?? ''),
                phone: (string) ($data['phone_number']['phone'] ?? '')
            );

            $addressDetails = null;
            if (!empty($data['client_address']['address_details'])) {
                $details = $data['client_address']['address_details'];
                $addressDetails = new AddressDetails(
                    street: isset($details['street']) ? (string) $details['street'] : null,
                    building: isset($details['building']) ? (string) $details['building'] : null,
                    flat: isset($details['flat']) ? (string) $details['flat'] : null,
                );
            }

            $address = new Address(
                countryCode: (string) ($data['client_address']['country_code'] ?? ''),
                city: (string) ($data['client_address']['city'] ?? ''),
                postalCode: (string) ($data['client_address']['postal_code'] ?? ''),
                streetLine: (string) ($data['client_address']['address'] ?? ''),
                details: $addressDetails,
            );

            return new self(
                name: (string) ($data['name'] ?? ''),
                surname: (string) ($data['surname'] ?? ''),
                mail: (string) ($data['mail'] ?? ''),
                phoneNumber: $phoneNumber,
                clientAddress: $address,
            );
        } catch (InvalidArgumentException $e) {
            throw new InvalidBasketException(sprintf('Invalid account info: %s', $e->getMessage()));
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Invalid account info: %s', $e->getMessage()));
        }
    }

    public function toDomain(): CustomerInfo
    {
        return new CustomerInfo(
            firstName: $this->name,
            lastName: $this->surname,
            email: $this->mail,
            phone: $this->phoneNumber,
            address: $this->clientAddress,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'surname' => $this->surname,
            'mail' => $this->mail,
            'phone_number' => $this->phoneNumber->toArray(),
            'client_address' => $this->clientAddress->toArray(),
        ];
    }
}
