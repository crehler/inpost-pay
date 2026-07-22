<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Entity;

use InvalidArgumentException;

use function in_array;
use function sprintf;

readonly class BasketNotice
{
    public const TYPE_ATTENTION = 'ATTENTION';
    public const TYPE_ERROR = 'ERROR';

    public function __construct(
        public string $type,
        public string $description,
    ) {
        $this->validate();
    }

    public static function attention(string $description): self
    {
        return new self(self::TYPE_ATTENTION, $description);
    }

    public static function error(string $description): self
    {
        return new self(self::TYPE_ERROR, $description);
    }

    public function isAttention(): bool
    {
        return $this->type === self::TYPE_ATTENTION;
    }

    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
        ];
    }

    private function validate(): void
    {
        if (!in_array($this->type, [self::TYPE_ATTENTION, self::TYPE_ERROR], true)) {
            throw new InvalidArgumentException(sprintf('Invalid notice type: %s. Must be ATTENTION or ERROR.', $this->type));
        }

        if (empty($this->description)) {
            throw new InvalidArgumentException('Notice description cannot be empty');
        }
    }
}
