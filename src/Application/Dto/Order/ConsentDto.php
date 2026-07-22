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
use Crehler\InpostPay\Domain\ValueObject\Order\Consent;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

use function sprintf;

readonly class ConsentDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Consent ID is required')]
        #[Assert\Type(type: 'string', message: 'Consent ID must be a string')]
        public string $consentId,

        #[Assert\NotNull(message: 'Consent acceptance status is required')]
        #[Assert\Type(type: 'boolean', message: 'Consent acceptance must be a boolean')]
        public bool $isAccepted,

        #[Assert\Type(type: 'string', message: 'Consent version must be a string')]
        public ?string $consentVersion = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            return new self(
                consentId: (string) ($data['consent_id'] ?? ''),
                isAccepted: (bool) ($data['is_accepted'] ?? false),
                consentVersion: isset($data['consent_version']) ? (string) $data['consent_version'] : null,
            );
        } catch (Throwable $e) {
            throw new InvalidBasketException(sprintf('Invalid consent data: %s', $e->getMessage()));
        }
    }

    public function toDomain(): Consent
    {
        return new Consent(
            id: $this->consentId,
            isAccepted: $this->isAccepted,
            version: $this->consentVersion,
        );
    }

    public function toArray(): array
    {
        return [
            'consent_id' => $this->consentId,
            'is_accepted' => $this->isAccepted,
            'consent_version' => $this->consentVersion,
        ];
    }
}
