<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Order;

readonly class InvoiceDetails
{
    public function __construct(
        public LegalForm $legalForm,
        public string $countryCode,
        public string $taxId,
        public ?string $taxIdPrefix = null,
        public ?string $companyName = null,
        public ?string $name = null,
        public ?string $surname = null,
        public ?string $city = null,
        public ?string $street = null,
        public ?string $building = null,
        public ?string $flat = null,
        public ?string $postalCode = null,
        public ?string $mail = null,
        public ?string $registrationDataEdited = null,
        public ?string $additionalInformation = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'legal_form' => $this->legalForm->value,
            'country_code' => $this->countryCode,
            'tax_id' => $this->taxId,
        ];

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

        return $data;
    }
}
