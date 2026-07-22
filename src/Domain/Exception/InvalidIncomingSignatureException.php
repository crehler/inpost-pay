<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Domain\Exception;

use RuntimeException;

use function sprintf;

class InvalidIncomingSignatureException extends RuntimeException
{
    public static function missingHeader(string $header): self
    {
        return new self(sprintf('Missing required signature header: %s', $header));
    }

    public static function timestampOutOfWindow(int $diffSeconds, int $maxSeconds): self
    {
        return new self(sprintf('Signature timestamp is %d seconds off (max %d)', $diffSeconds, $maxSeconds));
    }

    public static function publicKeyHashMismatch(): self
    {
        return new self('Public key hash does not match value advertised in x-public-key-hash header');
    }

    public static function verificationFailed(): self
    {
        return new self('RSA SHA256 signature verification failed');
    }

    public static function keyFetchFailed(string $reason): self
    {
        return new self(sprintf('Failed to fetch signing key from InPost: %s', $reason));
    }
}
