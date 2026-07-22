<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\Exception\InvalidBasketException;
use Crehler\InpostPay\Domain\ValueObject\Analytics\BasketAnalytics;
use Crehler\InpostPay\Infrastructure\Persistence\Entity\InpostBasketSessionEntity;
use DateTimeImmutable;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

use function array_merge;
use function sprintf;

readonly class InpostBasketSessionService
{
    public function __construct(
        private EntityRepository $inpostBasketSessionRepository,
    ) {
    }

    public function findOrCreateSession(
        string $basketId,
        string $salesChannelId,
        string $basketBindingApiKey,
        ?string $inpostBasketId = null,
        ?BasketAnalytics $analytics = null,
    ): InpostBasketSessionEntity {
        $existing = $this->getSessionByBasketId($basketId);

        if ($existing !== null) {
            $updateData = ['basketBindingApiKey' => $basketBindingApiKey];

            if ($inpostBasketId !== null) {
                $updateData['inpostBasketId'] = $inpostBasketId;
            }

            if ($analytics !== null && !$analytics->isEmpty()) {
                $updateData['analyticsClientId'] = $analytics->clientId;
                $updateData['analyticsGclid'] = $analytics->gclid;
                $updateData['analyticsFbclid'] = $analytics->fbclid;
            }

            $this->updateSession($basketId, $updateData);

            return $existing;
        }

        return $this->createSession(
            $basketId,
            $salesChannelId,
            $basketBindingApiKey,
            $inpostBasketId,
            $analytics,
        );
    }

    public function createSession(
        string $basketId,
        string $salesChannelId,
        string $basketBindingApiKey,
        ?string $inpostBasketId = null,
        ?BasketAnalytics $analytics = null,
    ): InpostBasketSessionEntity {
        $id = Uuid::randomHex();
        $now = new DateTimeImmutable();

        $data = [
            'id' => $id,
            'basketId' => $basketId,
            // The cart starts under the same token as the InPost-facing basketId.
            // It diverges only after a Shopware context-token change (see migrateSession).
            'cartToken' => $basketId,
            'salesChannelId' => $salesChannelId,
            'basketBindingApiKey' => $basketBindingApiKey,
            'inpostBasketId' => $inpostBasketId,
            'boundAt' => $now,
            'createdAt' => $now,
        ];

        if ($analytics !== null && !$analytics->isEmpty()) {
            $data['analyticsClientId'] = $analytics->clientId;
            $data['analyticsGclid'] = $analytics->gclid;
            $data['analyticsFbclid'] = $analytics->fbclid;
        }

        $this->inpostBasketSessionRepository->create([$data], Context::createDefaultContext());

        $entity = new InpostBasketSessionEntity();
        $entity->setUniqueIdentifier($id);
        $entity->basketId = $basketId;
        $entity->cartToken = $basketId;
        $entity->salesChannelId = $salesChannelId;
        $entity->basketBindingApiKey = $basketBindingApiKey;
        $entity->inpostBasketId = $inpostBasketId;
        $entity->boundAt = $now;
        $entity->createdAt = $now;

        if ($analytics !== null) {
            $entity->setAnalyticsClientId($analytics->clientId);
            $entity->setAnalyticsGclid($analytics->gclid);
            $entity->setAnalyticsFbclid($analytics->fbclid);
        }

        return $entity;
    }

    public function getSessionByBasketId(string $token): ?InpostBasketSessionEntity
    {
        // InPost always calls with the original merchant basket_id, so match that first.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('basketId', $token));
        $byBasketId = $this->inpostBasketSessionRepository->search($criteria, Context::createDefaultContext())->first();

        if ($byBasketId !== null) {
            return $byBasketId;
        }

        // After a Shopware context-token change (e.g. guest login) the live cart token
        // diverges from basketId; cart-driven calls (cart change, order creation) arrive
        // with the new token, so fall back to matching cartToken.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('cartToken', $token));

        return $this->inpostBasketSessionRepository->search($criteria, Context::createDefaultContext())->first();
    }

    public function getSessionByOrderId(string $orderId): ?InpostBasketSessionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        return $this->inpostBasketSessionRepository->search($criteria, Context::createDefaultContext())->first();
    }

    public function updateSession(string $basketId, array $data): void
    {
        $session = $this->getSessionByBasketId($basketId);

        if (!$session) {
            throw new InvalidBasketException(sprintf('Basket session not found for basketId: %s', $basketId));
        }

        $updateData = array_merge(['id' => $session->getId()], $data);

        $this->inpostBasketSessionRepository->update([$updateData], Context::createDefaultContext());
    }

    public function linkSessionToOrder(string $basketId, string $orderId): void
    {
        $this->updateSession($basketId, ['orderId' => $orderId]);
    }

    public function deleteSessionByBasketId(string $basketId): InpostBasketSessionEntity
    {
        $session = $this->getSessionByBasketId($basketId);

        if (!$session) {
            throw new InvalidBasketException(sprintf('Basket session not found for basketId: %s', $basketId));
        }

        $this->inpostBasketSessionRepository->delete(
            [['id' => $session->getId()]],
            Context::createDefaultContext()
        );

        return $session;
    }

    public function migrateSession(string $oldToken, string $newToken): void
    {
        $session = $this->getSessionByBasketId($oldToken);

        if ($session === null) {
            return;
        }

        // Only the Shopware cart token follows the context-token change. The InPost-facing
        // basketId stays immutable: InPost remembers it from bind time and uses it for
        // every get_basket / basket_event / confirmation call. Overwriting basketId here
        // (the previous behaviour) made InPost look up a token we no longer knew about,
        // causing 404 BASKET_NOT_FOUND on every subsequent call.
        if ($session->getCartToken() === $newToken) {
            return;
        }

        $this->inpostBasketSessionRepository->update([
            [
                'id' => $session->getId(),
                'cartToken' => $newToken,
            ],
        ], Context::createDefaultContext());
    }
}
