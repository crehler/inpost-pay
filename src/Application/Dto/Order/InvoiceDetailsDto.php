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
use Crehler\InpostPay\Domain\ValueObject\Order\{InvoiceDetails, LegalForm};
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;
use ValueError;

use function preg_replace;
use function sprintf;
use function trim;

readonly class InvoiceDetailsDto
{
    public function __construct(
        #[Assert\Choice(choices: ['PERSON', 'COMPANY'], message: 'Invalid legal form')]
        public ?string $legalForm = null,

        #[Assert\Type(type: 'string', message: 'Country code must be a string')]
        public ?string $countryCode = null,

        #[Assert\Type(type: 'string', message: 'Tax ID must be a string')]
        public ?string $taxId = null,

        #[Assert\Type(type: 'string', message: 'Tax ID prefix must be a string')]
        public ?string $taxIdPrefix = null,

        #[Assert\Type(type: 'string', message: 'Company name must be a string')]
        public ?string $companyName = null,

        #[Assert\Type(type: 'string', message: 'Name must be a string')]
        public ?string $name = null,

        #[Assert\Type(type: 'string', message: 'Surname must be a string')]
        public ?string $surname = null,

        #[Assert\Type(type: 'string', message: 'City must be a string')]
        public ?string $city = null,

        #[Assert\Type(type: 'string', message: 'Street must be a string')]
        public ?string $street = null,

        #[Assert\Type(type: 'string', message: 'Building must be a string')]
        public ?string $building = null,

        #[Assert\Type(type: 'string', message: 'Flat must be a string')]
        public ?string $flat = null,

        #[Assert\Type(type: 'string', message: 'Postal code must be a string')]
        public ?string $postalCode = null,

        #[Assert\Email(message: 'Invalid email format')]
        public ?string $mail = null,

        #[Assert\Type(type: 'string', message: 'Registration date edited must be a string')]
        public ?string $registrationDataEdited = null,

        #[Assert\Type(type: 'string', message: 'Additional information must be a string')]
        public ?string $additionalInformation = null,
    ) {
    }

    public function fullVatId(): ?string
    {
        $taxId = preg_replace('/\s+/', '', $this->taxId ?? '') ?? '';
        if ($taxId === '') {
            return null;
        }

        $prefix = preg_replace('/\s+/', '', $this->taxIdPrefix ?? '') ?? '';

        return $prefix . $taxId;
    }

    public function streetLine(): ?string
    {
        $street = trim(($this->street ?? '') . ' ' . ($this->building ?? ''));

        if ($street === '') {
            return null;
        }

        return $this->flat ? $street . '/' . $this->flat : $street;
    }

    public static function fromArray(array $data): self
    {
        try {
            if (empty($data)) {
                return new self();
            }

            return new self(
                legalForm: isset($data['legal_form']) ? (string) $data['legal_form'] : null,
                countryCode: isset($data['country_code']) ? (string) $data['country_code'] : null,
                taxId: isset($data['tax_id']) ? (string) $data['tax_id'] : null,
                taxIdPrefix: isset($data['tax_id_prefix']) ? (string) $data['tax_id_prefix'] : null,
                companyName: isset($data['company_name']) ? (string) $data['company_name'] : null,
                name: isset($data['name']) ? (string) $data['name'] : null,
                surname: isset($data['surname']) ? (string) $data['surname'] : null,
                city: isset($data['city']) ? (string) $data['city'] : null,
                street: isset($data['street']) ? (string) $data['street'] : null,
                building: isset($data['building']) ? (string) $data['building'] : null,
                flat: isset($data['flat']) ? (string) $data['flat'] : null,
                postalCode: isset($data['postal_code']) ? (string) $data['postal_code'] : null,
                mail: isset($data['mail']) ? (string) $data['mail'] : null,
                registrationDataEdited: isset($data['registration_data_edited']) ? (string) $data['registration_data_edited'] : null,
                additionalInformation: isset($data['additional_information']) ? (string) $data['additional_information'] : null,
            );
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Invalid invoice details: %s', $e->getMessage()));
        }
    }

    public function toDomain(): ?InvoiceDetails
    {
        if (empty($this->legalForm) && empty($this->countryCode) && empty($this->taxId)) {
            return null;
        }

        try {
            $legalForm = $this->legalForm ? LegalForm::from($this->legalForm) : LegalForm::PERSON;

            return new InvoiceDetails(
                legalForm: $legalForm,
                countryCode: $this->countryCode ?? '',
                taxId: $this->taxId ?? '',
                taxIdPrefix: $this->taxIdPrefix,
                companyName: $this->companyName,
                name: $this->name,
                surname: $this->surname,
                city: $this->city,
                street: $this->street,
                building: $this->building,
                flat: $this->flat,
                postalCode: $this->postalCode,
                mail: $this->mail,
                registrationDataEdited: $this->registrationDataEdited,
                additionalInformation: $this->additionalInformation,
            );
        } catch (ValueError $e) {
            throw new InvalidBasketException(sprintf('Invalid legal form: %s', $e->getMessage()));
        }
    }

    public function toArray(): ?array
    {
        if (empty($this->legalForm) && empty($this->countryCode) && empty($this->taxId)) {
            return null;
        }

        $data = [];

        if ($this->legalForm) {
            $data['legal_form'] = $this->legalForm;
        }
        if ($this->countryCode) {
            $data['country_code'] = $this->countryCode;
        }
        if ($this->taxId) {
            $data['tax_id'] = $this->taxId;
        }
        if ($this->taxIdPrefix) {
            $data['tax_id_prefix'] = $this->taxIdPrefix;
        }
        if ($this->companyName) {
            $data['company_name'] = $this->companyName;
        }
        if ($this->name) {
            $data['name'] = $this->name;
        }
        if ($this->surname) {
            $data['surname'] = $this->surname;
        }
        if ($this->city) {
            $data['city'] = $this->city;
        }
        if ($this->street) {
            $data['street'] = $this->street;
        }
        if ($this->building) {
            $data['building'] = $this->building;
        }
        if ($this->flat) {
            $data['flat'] = $this->flat;
        }
        if ($this->postalCode) {
            $data['postal_code'] = $this->postalCode;
        }
        if ($this->mail) {
            $data['mail'] = $this->mail;
        }
        if ($this->registrationDataEdited) {
            $data['registration_data_edited'] = $this->registrationDataEdited;
        }
        if ($this->additionalInformation) {
            $data['additional_information'] = $this->additionalInformation;
        }

        return !empty($data) ? $data : null;
    }
}
