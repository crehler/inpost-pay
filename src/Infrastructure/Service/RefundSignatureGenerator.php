<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Service;

use function explode;
use function hash;
use function is_array;
use function is_bool;
use function is_float;
use function is_scalar;
use function ksort;
use function number_format;
use function rtrim;
use function str_contains;
use function strlen;
use function strtolower;

final readonly class RefundSignatureGenerator
{
    public function generate(
        string $commandId,
        string $transactionId,
        array $requestBody,
        string $merchantSecret,
    ): string {
        $stringToHash = $commandId . $transactionId;

        $flattenedValues = $this->flattenAndSortValues($requestBody);

        foreach ($flattenedValues as $value) {
            $stringToHash .= $this->normalizeValue($value);
        }

        $stringToHash .= $merchantSecret;

        return strtolower(hash('sha512', $stringToHash));
    }

    private function flattenAndSortValues(array $data, string $prefix = ''): array
    {
        $result = [];

        ksort($data);

        foreach ($data as $key => $value) {
            if ($key === 'signature') {
                continue;
            }

            $fullKey = $prefix !== '' ? $prefix . '.' . $key : $key;

            if (is_array($value)) {
                $nested = $this->flattenAndSortValues($value, $fullKey);
                foreach ($nested as $nestedValue) {
                    $result[] = $nestedValue;
                }
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    private function normalizeValue(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_float($value) => $this->formatFloat($value),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    private function formatFloat(float $value): string
    {
        $formatted = number_format($value, 10, '.', '');
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');

        if (str_contains((string) $value, '.')) {
            $parts = explode('.', $formatted);
            if (isset($parts[1]) && strlen($parts[1]) < 2) {
                $formatted = number_format($value, 2, '.', '');
            }
        }

        return $formatted;
    }
}
