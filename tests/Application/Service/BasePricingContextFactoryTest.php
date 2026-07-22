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
use Crehler\InpostPay\Infrastructure\Persistence\Repository\InpostPayPaymentMethodResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BasePricingContextFactoryTest extends TestCase
{
    public function testCreate_cartAlreadyUsesBaseMethod_returnsSameContext(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('base-method');
        $paymentMethod->setActive(true);

        $resolver = $this->createMock(InpostPayPaymentMethodResolver::class);
        $resolver->method('getBasePaymentMethod')->willReturn($paymentMethod);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn($this->createMock(Context::class));
        $context->method('getPaymentMethod')->willReturn($paymentMethod);

        $factory = new BasePricingContextFactory($resolver, $this->createMock(AbstractRuleLoader::class), $this->createMock(LoggerInterface::class));

        // No swap needed - the original context is returned untouched.
        self::assertSame($context, $factory->create($this->createMock(Cart::class), $context));
    }

    public function testCreate_resolverFails_returnsSameContext(): void
    {
        $resolver = $this->createMock(InpostPayPaymentMethodResolver::class);
        $resolver->method('getBasePaymentMethod')->willThrowException(new RuntimeException('no method'));

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn($this->createMock(Context::class));

        $factory = new BasePricingContextFactory($resolver, $this->createMock(AbstractRuleLoader::class), $this->createMock(LoggerInterface::class));

        self::assertSame($context, $factory->create($this->createMock(Cart::class), $context));
    }
}
