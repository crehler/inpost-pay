<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto\EventData;

use Crehler\InpostPay\Domain\Exception\InvalidBasketException;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use ValueError;

use function sprintf;

readonly class PromoCodeEventData
{
    public const ACTION_ADD = 'ADD';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_REMOVE = 'REMOVE';

    public function __construct(
        #[Assert\NotBlank(message: 'Promo code is required')]
        #[Assert\Type(type: 'string', message: 'Promo code must be a string')]
        public string $code,

        #[Assert\NotBlank(message: 'Action is required')]
        #[Assert\Choice(
            choices: [self::ACTION_ADD, self::ACTION_UPDATE, self::ACTION_REMOVE],
            message: 'Action must be one of: ADD, UPDATE, REMOVE'
        )]
        public string $action = self::ACTION_ADD,

        #[Assert\Type(type: 'string', message: 'Description must be a string')]
        public ?string $description = null,

        #[Assert\Type(type: 'string', message: 'Discount value must be a string')]
        public ?string $discountValue = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        try {
            return new self(
                code: $data['promo_code_value'] ?? $data['code'] ?? '',
                action: $data['action'] ?? self::ACTION_ADD,
                description: $data['name'] ?? $data['description'] ?? null,
                discountValue: $data['discount_value'] ?? null,
            );
        } catch (ValueError|InvalidArgumentException $e) {
            throw new InvalidBasketException(sprintf('Invalid promo code event data: %s', $e->getMessage()));
        }
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'action' => $this->action,
            'description' => $this->description,
            'discount_value' => $this->discountValue,
        ];
    }

    public function isAdd(): bool
    {
        return $this->action === self::ACTION_ADD;
    }

    public function isRemove(): bool
    {
        return $this->action === self::ACTION_REMOVE;
    }

    public function isUpdate(): bool
    {
        return $this->action === self::ACTION_UPDATE;
    }
}
