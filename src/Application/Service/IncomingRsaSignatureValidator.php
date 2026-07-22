<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\Exception\InvalidIncomingSignatureException;
use DateTimeImmutable;
use DateTimeZone;
use OpenSSLAsymmetricKey;

use function abs;
use function base64_decode;
use function base64_encode;
use function chunk_split;
use function hash;
use function openssl_pkey_get_public;
use function openssl_verify;
use function sprintf;

final readonly class IncomingRsaSignatureValidator
{
    private const MAX_TIMESTAMP_DRIFT_SECONDS = 240;

    public function __construct(
        private SigningKeyProvider $keyProvider,
    ) {
    }

    public function validate(
        string $signature,
        string $timestamp,
        string $keyVersion,
        string $keyHash,
        string $requestBody,
    ): void {
        $this->verifyTimestamp($timestamp);

        $signingKey = $this->keyProvider->getKeyForVersion($keyVersion, $keyHash);

        $digest = base64_encode(hash('sha256', $requestBody, true));
        $signatureString = sprintf(
            '%s,%s,%s,%s',
            $digest,
            $signingKey->merchantExternalId,
            $keyVersion,
            $timestamp,
        );
        $signatureStringBase64 = base64_encode($signatureString);

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            throw InvalidIncomingSignatureException::verificationFailed();
        }

        $publicKeyResource = $this->loadPublicKey($signingKey->publicKeyDer());

        $result = openssl_verify(
            $signatureStringBase64,
            $decodedSignature,
            $publicKeyResource,
            OPENSSL_ALGO_SHA256,
        );

        if ($result !== 1) {
            throw InvalidIncomingSignatureException::verificationFailed();
        }
    }

    private function verifyTimestamp(string $timestamp): void
    {
        $signatureTime = new DateTimeImmutable($timestamp);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $diff = abs($now->getTimestamp() - $signatureTime->getTimestamp());

        if ($diff > self::MAX_TIMESTAMP_DRIFT_SECONDS) {
            throw InvalidIncomingSignatureException::timestampOutOfWindow((int) $diff, self::MAX_TIMESTAMP_DRIFT_SECONDS);
        }
    }

    private function loadPublicKey(string $derEncodedKey): OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($derEncodedKey), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $resource = openssl_pkey_get_public($pem);
        if ($resource === false) {
            throw InvalidIncomingSignatureException::verificationFailed();
        }

        return $resource;
    }
}
