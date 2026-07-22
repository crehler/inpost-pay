<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Infrastructure\Persistence\Repository\InpostPayPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\{AbstractRuleLoader, Cart, RuleLoader};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

readonly class BasePricingContextFactory
{
    public function __construct(
        private InpostPayPaymentMethodResolver $paymentMethodResolver,
        #[Autowire(service: RuleLoader::class)]
        private AbstractRuleLoader $ruleLoader,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Context priced with the InPost Pay (non-COD) payment method, so a payment-based
     * shipping surcharge is not folded into the base price. Returns the original
     * context unchanged when already non-COD or unresolvable.
     */
    public function create(Cart $cart, SalesChannelContext $context): SalesChannelContext
    {
        try {
            $paymentMethod = $this->paymentMethodResolver->getBasePaymentMethod($context->getContext());
        } catch (Throwable $e) {
            $this->logger->warning(
                'InPost Pay: could not resolve the base payment method for delivery pricing, using the original context.',
                ['exception' => $e->getMessage()]
            );

            return $context;
        }

        if ($paymentMethod === null || $context->getPaymentMethod()->getId() === $paymentMethod->getId()) {
            return $context;
        }

        $pricingContext = clone $context;
        $pricingContext->assign(['paymentMethod' => $paymentMethod]);

        // Re-match cart rules for the swapped payment method (mirrors CartRuleLoader).
        $rules = $this->ruleLoader->load($context->getContext())->filterForContext();
        $pricingContext->setRuleIds($rules->filterMatchingRules($cart, $pricingContext)->getIds());

        return $pricingContext;
    }
}
