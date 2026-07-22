<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use function array_filter;

final readonly class WebhookResult
{
    public function __construct(
        public string $status,
        public string $message,
        public array $additionalData = [],
    ) {
    }

    public static function acknowledged(string $message, array $additionalData = []): self
    {
        return new self(
            status: 'acknowledged',
            message: $message,
            additionalData: $additionalData,
        );
    }

    public static function failed(string $message, array $additionalData = []): self
    {
        return new self(
            status: 'failed',
            message: $message,
            additionalData: $additionalData,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'message' => $this->message,
            ...$this->additionalData,
        ]);
    }
}
