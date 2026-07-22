<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Application\Service;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Infrastructure\Persistence\Entity\InpostBasketSessionEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

final class InpostBasketSessionServiceTest extends TestCase
{
    public function testCreateSessionInitialisesCartTokenToBasketId(): void
    {
        $repository = $this->createMock(EntityRepository::class);

        $writtenEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $captured = null;
        $repository->expects(self::once())
            ->method('create')
            ->willReturnCallback(function (array $payload) use (&$captured, $writtenEvent) {
                $captured = $payload[0];

                return $writtenEvent;
            });

        $service = new InpostBasketSessionService($repository);
        $service->createSession('basket-AAA', 'sc-1', 'api-key');

        self::assertSame('basket-AAA', $captured['basketId']);
        self::assertSame('basket-AAA', $captured['cartToken'], 'cartToken should start equal to basketId');
    }

    public function testMigrateSessionUpdatesCartTokenAndLeavesBasketIdImmutable(): void
    {
        $session = $this->makeSession('id-1', basketId: 'OLD', cartToken: 'OLD');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->resultOf($session));

        $writtenEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $captured = null;
        $repository->expects(self::once())
            ->method('update')
            ->willReturnCallback(function (array $payload) use (&$captured, $writtenEvent) {
                $captured = $payload[0];

                return $writtenEvent;
            });

        $service = new InpostBasketSessionService($repository);
        $service->migrateSession('OLD', 'NEW');

        self::assertSame('id-1', $captured['id']);
        self::assertSame('NEW', $captured['cartToken'], 'cartToken must follow the new Shopware token');
        self::assertArrayNotHasKey('basketId', $captured, 'basketId must stay immutable (InPost external id)');
    }

    public function testMigrateSessionNoopWhenNothingFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->resultOf(null));
        $repository->expects(self::never())->method('update');

        $service = new InpostBasketSessionService($repository);
        $service->migrateSession('OLD', 'NEW');
    }

    public function testMigrateSessionNoopWhenCartTokenAlreadyMatches(): void
    {
        $session = $this->makeSession('id-1', basketId: 'OLD', cartToken: 'NEW');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->resultOf($session));
        $repository->expects(self::never())->method('update');

        $service = new InpostBasketSessionService($repository);
        $service->migrateSession('NEW', 'NEW');
    }

    public function testGetSessionByBasketIdPrefersBasketIdMatch(): void
    {
        $session = $this->makeSession('id-1', basketId: 'OLD', cartToken: 'NEW');

        $repository = $this->createMock(EntityRepository::class);
        // First search (by basketId) already hits.
        $repository->expects(self::once())
            ->method('search')
            ->willReturn($this->resultOf($session));

        $service = new InpostBasketSessionService($repository);
        $found = $service->getSessionByBasketId('OLD');

        self::assertSame($session, $found);
    }

    public function testGetSessionByBasketIdFallsBackToCartToken(): void
    {
        $session = $this->makeSession('id-1', basketId: 'OLD', cartToken: 'NEW');

        $repository = $this->createMock(EntityRepository::class);
        // First search (basketId = NEW) misses, second (cartToken = NEW) hits.
        $repository->expects(self::exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls($this->resultOf(null), $this->resultOf($session));

        $service = new InpostBasketSessionService($repository);
        $found = $service->getSessionByBasketId('NEW');

        self::assertSame($session, $found);
    }

    private function makeSession(string $id, string $basketId, string $cartToken): InpostBasketSessionEntity
    {
        $session = new InpostBasketSessionEntity();
        $session->setId($id);
        $session->setUniqueIdentifier($id);
        $session->setBasketId($basketId);
        $session->setCartToken($cartToken);
        $session->setSalesChannelId('sc-1');

        return $session;
    }

    private function resultOf(?InpostBasketSessionEntity $entity): EntitySearchResult
    {
        $entities = new EntityCollection($entity !== null ? [$entity] : []);

        return new EntitySearchResult(
            InpostBasketSessionEntity::class,
            $entity !== null ? 1 : 0,
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
