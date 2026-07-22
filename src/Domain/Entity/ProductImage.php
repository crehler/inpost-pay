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

use function filter_var;
use function sprintf;

readonly class ProductImage
{
    public function __construct(
        public string $smallSize,
        public string $normalSize,
    ) {
        $this->validate();
    }

    public function toArray(): array
    {
        return [
            'small_size' => $this->smallSize,
            'normal_size' => $this->normalSize,
        ];
    }

    private function validate(): void
    {
        if (empty($this->smallSize)) {
            throw new InvalidArgumentException('Small size image URL cannot be empty');
        }

        if (empty($this->normalSize)) {
            throw new InvalidArgumentException('Normal size image URL cannot be empty');
        }

        if (!filter_var($this->smallSize, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid small size URL: %s', $this->smallSize));
        }

        if (!filter_var($this->normalSize, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid normal size URL: %s', $this->normalSize));
        }
    }
}
