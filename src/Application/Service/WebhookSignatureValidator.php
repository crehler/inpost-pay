<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\ValueObject\WebhookEventType;

use function array_key_exists;
use function explode;
use function hash;
use function hash_equals;
use function is_array;
use function is_bool;
use function is_scalar;
use function strtolower;

final readonly class WebhookSignatureValidator
{
    public function validate(
        string $apiVersion,
        array $payload,
        string $receivedSignature,
        string $merchantSecret,
        WebhookEventType $eventType,
    ): bool {
        $stringToHash = $apiVersion;

        $fieldPaths = $eventType->getSignatureFields();

        foreach ($fieldPaths as $fieldPath) {
            $value = $this->extractNestedValue($payload, $fieldPath);
            $stringToHash .= $this->normalizeValue($value);
        }

        $stringToHash .= $merchantSecret;

        $expectedSignature = hash('sha512', $stringToHash);

        return hash_equals($expectedSignature, strtolower($receivedSignature));
    }

    private function extractNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return '';
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function normalizeValue(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
