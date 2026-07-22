<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Dto;

use Crehler\InpostPay\Domain\ValueObject\WebhookEventType;
use InvalidArgumentException;
use ValueError;

use function sprintf;

final readonly class WebhookPayloadDto
{
    public function __construct(
        public WebhookEventType $eventType,
        public array $eventData,
        public string $apiVersion,
        public string $signature,
    ) {
    }

    public static function fromRequest(
        array $payload,
        string $apiVersion,
        string $signature,
    ): self {
        try {
            $eventType = WebhookEventType::from($payload['eventType'] ?? '');

            return new self(
                eventType: $eventType,
                eventData: $payload['eventData'] ?? [],
                apiVersion: $apiVersion,
                signature: $signature,
            );
        } catch (ValueError $e) {
            throw new InvalidArgumentException(sprintf('Invalid webhook event type: %s', $payload['eventType'] ?? 'unknown'), 0, $e);
        }
    }

    public function toArray(): array
    {
        return [
            'eventType' => $this->eventType->value,
            'eventData' => $this->eventData,
        ];
    }
}
