<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Emitter;

use Crehler\InpostPay\Application\Provider\RelatedProductsProviderInterface;
use Crehler\InpostPay\Domain\Entity\BasketProduct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Throwable;

use function get_debug_type;
use function iterator_to_array;
use function usort;

readonly class RelatedProductsEmitter
{
    public function __construct(
        #[TaggedIterator('inpost_pay.related_products_provider')]
        private iterable $providers,
        private LoggerInterface $logger,
    ) {
    }

    public function emit(Cart $cart, SalesChannelContext $context): array
    {
        $relatedProducts = [];
        $providers = $this->getSortedProviders();

        foreach ($providers as $provider) {
            try {
                $products = $provider->provide($cart, $context);

                foreach ($products as $product) {
                    if (!$product instanceof BasketProduct) {
                        $this->logger->warning('[InpostPay][RelatedProductsEmitter] Provider returned invalid product type', [
                            'provider' => $provider::class,
                            'type' => get_debug_type($product),
                        ]);
                        continue;
                    }

                    $relatedProducts[] = $product;
                }
            } catch (Throwable $e) {
                $this->logger->error('[InpostPay][RelatedProductsEmitter] Provider failed', [
                    'provider' => $provider::class,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $relatedProducts;
    }

    private function getSortedProviders(): array
    {
        $providers = iterator_to_array($this->providers);

        usort($providers, static function (
            RelatedProductsProviderInterface $a,
            RelatedProductsProviderInterface $b,
        ): int {
            return $b->getPriority() <=> $a->getPriority();
        });

        return $providers;
    }
}
