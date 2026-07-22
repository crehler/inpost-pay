<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject\Consent;

readonly class ConsentTranslation
{
    public function __construct(
        public string $description,
        public string $labelLink,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'] ?? '',
            labelLink: $data['labelLink'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'labelLink' => $this->labelLink,
        ];
    }
}
