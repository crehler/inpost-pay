<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Consent;

/**
 * Represents a consent ready to be sent to InPost API.
 */
readonly class BasketConsent
{
    /**
     * @param array<array{id: string, consent_link: string, label_link: string|null}> $additionalConsentLinks
     */
    public function __construct(
        public string $consentId,
        public string $consentLink,
        public string $consentDescription,
        public string $consentVersion,
        public ConsentRequirementType $requirementType,
        public string $labelLink,
        public array $additionalConsentLinks = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'consent_id' => $this->consentId,
            'consent_link' => $this->consentLink,
            'consent_description' => $this->consentDescription,
            'consent_version' => $this->consentVersion,
            'requirement_type' => $this->requirementType->value,
            'label_link' => $this->labelLink,
            'additional_consent_links' => $this->additionalConsentLinks,
        ];
    }
}
