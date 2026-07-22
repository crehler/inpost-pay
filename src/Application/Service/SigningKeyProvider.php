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
use Crehler\InpostPay\Domain\ValueObject\SigningKey;
use Crehler\InpostPay\Infrastructure\Client\InpostPayClient;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function hash_equals;
use function is_array;

final readonly class SigningKeyProvider
{
    private const CACHE_KEY_PREFIX = 'inpost_pay_signing_key_';
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private InpostPayClient $client,
        private InpostPayAuthenticator $authenticator,
        private InpostPayConfigProvider $configProvider,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function getKeyForVersion(string $version, string $expectedHash): SigningKey
    {
        $cached = $this->getFromCache($version);
        if ($cached !== null && hash_equals($cached->publicKeySha256(), $expectedHash)) {
            return $cached;
        }

        $key = $this->fetchKey($version);

        if (!hash_equals($key->publicKeySha256(), $expectedHash)) {
            throw InvalidIncomingSignatureException::publicKeyHashMismatch();
        }

        $this->saveToCache($key);

        return $key;
    }

    private function fetchKey(string $version): SigningKey
    {
        try {
            $auth = $this->authenticator->authenticate();
            $config = $this->configProvider->getWidgetConfig();

            $data = $this->client->fetchSigningKey($version, $auth->token, $config->mode);
        } catch (Throwable $e) {
            throw InvalidIncomingSignatureException::keyFetchFailed($e->getMessage());
        }

        $publicKey = $data['public_key'] ?? null;
        if (!is_array($publicKey) || !isset($publicKey['public_key_base64'], $data['merchant_external_id'])) {
            throw InvalidIncomingSignatureException::keyFetchFailed('Response missing public_key.public_key_base64 or merchant_external_id');
        }

        return new SigningKey(
            version: $version,
            publicKeyBase64: (string) $publicKey['public_key_base64'],
            merchantExternalId: (string) $data['merchant_external_id'],
        );
    }

    private function getFromCache(string $version): ?SigningKey
    {
        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $version);
        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return $value instanceof SigningKey ? $value : null;
    }

    private function saveToCache(SigningKey $key): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $key->version);
        $item->set($key);
        $item->expiresAfter(self::CACHE_TTL_SECONDS);
        $this->cache->save($item);
    }
}
