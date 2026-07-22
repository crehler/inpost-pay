<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\Exception\InpostPayEndpointException;
use Crehler\InpostPay\Domain\ValueObject\AuthToken;
use Crehler\InpostPay\Infrastructure\Client\InpostPayClient;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use DateTime;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

final class InpostPayAuthenticator
{
    /**
     * @var string
     */
    private const CACHE_KEY = 'inpost_pay_oauth_token';

    public function __construct(
        private readonly InpostPayConfigProvider $configProvider,
        private readonly InpostPayClient $oAuth2Client,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function authenticate(): AuthToken
    {
        $cachedToken = $this->getFromCache();

        if ($cachedToken !== null && !$cachedToken->isExpired()) {
            return $cachedToken;
        }

        $config = $this->configProvider->getWidgetConfig();

        try {
            $oAuth2Token = $this->oAuth2Client->getOAuth2Token($config);
        } catch (Throwable $e) {
            throw new InpostPayEndpointException('Failed to fetch OAuth2 token from InPost', previous: $e);
        }

        $expiresAt = DateTime::createFromFormat(
            'U',
            (string) $oAuth2Token->getExpires(),
        );

        if ($expiresAt === false) {
            throw new InpostPayEndpointException('Invalid token expiration time');
        }

        $authToken = new AuthToken(
            token: $oAuth2Token->getToken(),
            expiresAt: $expiresAt,
        );

        $this->saveToCache($authToken);

        return $authToken;
    }

    public function isAuthenticated(): bool
    {
        $token = $this->getFromCache();

        return $token !== null && !$token->isExpired();
    }

    private function getFromCache(): ?AuthToken
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    private function saveToCache(AuthToken $token): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        $item->set($token);
        $item->expiresAt($token->expiresAt);

        $this->cache->save($item);
    }
}
