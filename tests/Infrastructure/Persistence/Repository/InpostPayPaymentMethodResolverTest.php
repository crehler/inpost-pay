<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Infrastructure\Persistence\Repository;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Domain\ValueObject\PaymentType;
use Crehler\InpostPay\Infrastructure\Checkout\{InpostPayCodPaymentHandler, InpostPayPaymentHandler};
use Crehler\InpostPay\Infrastructure\Persistence\Repository\InpostPayPaymentMethodResolver;
use Crehler\InpostPay\Infrastructure\Provider\InpostPayConfigProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class InpostPayPaymentMethodResolverTest extends TestCase
{
    private const DEFAULT_METHOD_ID = 'aaaa1111aaaa1111aaaa1111aaaa1111';
    private const COD_METHOD_ID = 'bbbb2222bbbb2222bbbb2222bbbb2222';

    public function testReturnsDefaultMethodForOnlinePayment(): void
    {
        $resolver = $this->createResolver(codConfigEnabled: true, methodIdsByHandler: [
            InpostPayPaymentHandler::class => self::DEFAULT_METHOD_ID,
            InpostPayCodPaymentHandler::class => self::COD_METHOD_ID,
        ]);

        $id = $resolver->getPaymentMethodId($this->createMock(Context::class), PaymentType::BLIK_CODE);

        $this->assertSame(self::DEFAULT_METHOD_ID, $id);
    }

    public function testReturnsCodMethodForCashOnDeliveryWhenEnabled(): void
    {
        $resolver = $this->createResolver(codConfigEnabled: true, methodIdsByHandler: [
            InpostPayPaymentHandler::class => self::DEFAULT_METHOD_ID,
            InpostPayCodPaymentHandler::class => self::COD_METHOD_ID,
        ]);

        $id = $resolver->getPaymentMethodId($this->createMock(Context::class), PaymentType::CASH_ON_DELIVERY);

        $this->assertSame(self::COD_METHOD_ID, $id);
    }

    public function testReturnsDefaultMethodForCashOnDeliveryWhenDisabled(): void
    {
        $resolver = $this->createResolver(codConfigEnabled: false, methodIdsByHandler: [
            InpostPayPaymentHandler::class => self::DEFAULT_METHOD_ID,
            InpostPayCodPaymentHandler::class => self::COD_METHOD_ID,
        ]);

        $id = $resolver->getPaymentMethodId($this->createMock(Context::class), PaymentType::CASH_ON_DELIVERY);

        $this->assertSame(self::DEFAULT_METHOD_ID, $id);
    }

    public function testFallsBackToDefaultMethodWhenCodMethodMissing(): void
    {
        $resolver = $this->createResolver(codConfigEnabled: true, methodIdsByHandler: [
            InpostPayPaymentHandler::class => self::DEFAULT_METHOD_ID,
        ]);

        $id = $resolver->getPaymentMethodId($this->createMock(Context::class), PaymentType::CASH_ON_DELIVERY);

        $this->assertSame(self::DEFAULT_METHOD_ID, $id);
    }

    public function testReturnsDefaultMethodWhenPaymentTypeOmitted(): void
    {
        $resolver = $this->createResolver(codConfigEnabled: true, methodIdsByHandler: [
            InpostPayPaymentHandler::class => self::DEFAULT_METHOD_ID,
            InpostPayCodPaymentHandler::class => self::COD_METHOD_ID,
        ]);

        $id = $resolver->getPaymentMethodId($this->createMock(Context::class));

        $this->assertSame(self::DEFAULT_METHOD_ID, $id);
    }

    public function testThrowsWhenDefaultMethodMissing(): void
    {
        $resolver = $this->createResolver(codConfigEnabled: false, methodIdsByHandler: []);

        $this->expectException(RuntimeException::class);

        $resolver->getPaymentMethodId($this->createMock(Context::class));
    }

    /**
     * @param array<class-string, string> $methodIdsByHandler
     */
    private function createResolver(bool $codConfigEnabled, array $methodIdsByHandler): InpostPayPaymentMethodResolver
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')->willReturnCallback(
            function ($criteria) use ($methodIdsByHandler): IdSearchResult {
                $handler = null;
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter->getField() === 'handlerIdentifier') {
                        $handler = $filter->getValue();
                    }
                }

                $id = $methodIdsByHandler[$handler] ?? null;
                $ids = $id !== null
                    ? [['primaryKey' => $id, 'data' => []]]
                    : [];

                return new IdSearchResult(count($ids), $ids, $criteria, $this->createMock(Context::class));
            }
        );

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(
            fn (string $key) => $key === 'InpostPay.config.codSeparatePaymentMethodEnabled' ? $codConfigEnabled : null
        );

        $configProvider = new InpostPayConfigProvider(
            $systemConfig,
            $this->createMock(InpostBasketSessionService::class),
        );

        return new InpostPayPaymentMethodResolver(
            $repository,
            $configProvider,
            $this->createMock(LoggerInterface::class),
        );
    }
}
