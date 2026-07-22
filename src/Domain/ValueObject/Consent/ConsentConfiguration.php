<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Consent;

use function reset;

readonly class ConsentConfiguration
{
    /**
     * @param array<string, array{description: string, labelLink: string}> $translations
     */
    public function __construct(
        public string $id,
        public bool $enabled,
        public ConsentRequirementType $requirementType,
        public string $version,
        public ConsentLinkType $linkType,
        public ?string $cmsPageId,
        public ?string $externalLink,
        public array $translations,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            enabled: $data['enabled'] ?? false,
            requirementType: ConsentRequirementType::from($data['requirementType'] ?? 'OPTIONAL'),
            version: $data['version'] ?? '1.0',
            linkType: ConsentLinkType::from($data['linkType'] ?? 'external'),
            cmsPageId: $data['cmsPageId'] ?? null,
            externalLink: $data['externalLink'] ?? null,
            translations: $data['translations'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'enabled' => $this->enabled,
            'requirementType' => $this->requirementType->value,
            'version' => $this->version,
            'linkType' => $this->linkType->value,
            'cmsPageId' => $this->cmsPageId,
            'externalLink' => $this->externalLink,
            'translations' => $this->translations,
        ];
    }

    public function getTranslation(string $locale, string $fallbackLocale = 'pl-PL'): ConsentTranslation
    {
        if (isset($this->translations[$locale])) {
            return ConsentTranslation::fromArray($this->translations[$locale]);
        }

        if (isset($this->translations[$fallbackLocale])) {
            return ConsentTranslation::fromArray($this->translations[$fallbackLocale]);
        }

        $firstTranslation = reset($this->translations);
        if ($firstTranslation !== false) {
            return ConsentTranslation::fromArray($firstTranslation);
        }

        return new ConsentTranslation(
            description: '',
            labelLink: '',
        );
    }
}
