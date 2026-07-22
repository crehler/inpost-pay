<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Tests\Application\Service;

use Crehler\InpostPay\Application\Service\BasePricingContextFactory;
use Crehler\InpostPay\Application\Service\CartDataExtractor;
use Crehler\InpostPay\Application\Service\OrderService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ServiceWiringTest extends TestCase
{
    use KernelTestBehaviour;

    // Catches DI type mismatches that compile fine but fail at runtime instantiation.
    public function testCodPricingServicesAreInstantiable(): void
    {
        self::assertInstanceOf(BasePricingContextFactory::class, self::getContainer()->get(BasePricingContextFactory::class));
        self::assertInstanceOf(CartDataExtractor::class, self::getContainer()->get(CartDataExtractor::class));
        self::assertInstanceOf(OrderService::class, self::getContainer()->get(OrderService::class));
    }
}
