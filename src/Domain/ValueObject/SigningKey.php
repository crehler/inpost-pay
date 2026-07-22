<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\ValueObject;

use function base64_decode;
use function hash;

final readonly class SigningKey
{
    public function __construct(
        public string $version,
        public string $publicKeyBase64,
        public string $merchantExternalId,
    ) {
    }

    public function publicKeySha256(): string
    {
        return hash('sha256', $this->publicKeyBase64);
    }

    public function publicKeyDer(): string
    {
        return (string) base64_decode($this->publicKeyBase64, true);
    }
}
