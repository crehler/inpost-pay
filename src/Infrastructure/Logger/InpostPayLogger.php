<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Logger;

use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use Psr\Log\{AbstractLogger, LoggerInterface};
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function mb_strrpos;
use function mb_substr;
use function preg_replace;
use function str_repeat;
use function str_replace;
use function strtolower;

/**
 * The plugin's only logger. Bound to $logger for every InpostPay service (see
 * services.yaml), so this wraps the real monolog channel with two things every
 * call site gets for free: a merchant-configurable minimum level, and automatic
 * redaction of PII in the log context (name/phone/address/email fields).
 */
final class InpostPayLogger extends AbstractLogger implements ResetInterface
{
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    private const SENSITIVE_KEYS = [
        'name', 'firstname', 'lastname', 'surname',
        'phone', 'phonenumber', 'customerphone',
        'address', 'street', 'building', 'flat', 'city', 'postalcode',
        'taxid', 'taxidprefix', 'companyname',
    ];

    private const SENSITIVE_EMAIL_KEYS = ['email', 'mail', 'digitaldeliveryemail'];

    private ?int $minLevel = null;

    public function __construct(
        #[Autowire(service: 'monolog.logger.crehler_inpostpay')]
        private readonly LoggerInterface $logger,
        private readonly InpostPayConfigProvider $configProvider,
    ) {
    }

    public function reset(): void
    {
        $this->minLevel = null;
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelName = is_string($level) ? $level : (string) $level;

        if ((self::LEVELS[$levelName] ?? self::LEVELS['debug']) < $this->getMinLevel()) {
            return;
        }

        $this->logger->log($level, $message, $this->redact($context));
    }

    private function getMinLevel(): int
    {
        if ($this->minLevel === null) {
            $this->minLevel = self::LEVELS[$this->configProvider->getLogLevel()] ?? self::LEVELS['error'];
        }

        return $this->minLevel;
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null) {
            $normalizedKey = $this->normalizeKey($key);

            if (in_array($normalizedKey, self::SENSITIVE_EMAIL_KEYS, true)) {
                return is_string($value) ? $this->maskEmail($value) : '[REDACTED]';
            }

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                return is_string($value) ? $this->maskValue($value) : '[REDACTED]';
            }
        }

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $k => $v) {
                $redacted[$k] = $this->redact($v, is_string($k) ? $k : null);
            }

            return $redacted;
        }

        return $value;
    }

    /**
     * Normalizes a key so both snake_case and camelCase field name variants
     * (e.g. "phone_number" and "phoneNumber") match the same sensitive-key entry.
     */
    private function normalizeKey(string $key): string
    {
        $snakeCased = preg_replace('/(?<!^)(?=[A-Z])/', '_', $key);

        return strtolower(str_replace(['_', '-'], '', $snakeCased));
    }

    private function maskValue(string $value): string
    {
        $length = mb_strlen($value);
        if ($length === 0) {
            return $value;
        }

        $visible = match (true) {
            $length <= 4 => 1,
            $length === 5 => 2,
            default => 3,
        };

        return mb_substr($value, 0, $visible) . str_repeat('*', $length - $visible);
    }

    private function maskEmail(string $value): string
    {
        $atPos = mb_strrpos($value, '@');
        if ($atPos === false) {
            return $this->maskValue($value);
        }

        $local = mb_substr($value, 0, $atPos);
        $domain = mb_substr($value, $atPos);

        return $this->maskValue($local) . $domain;
    }
}
