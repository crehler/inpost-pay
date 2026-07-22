<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Provider;

use Crehler\InpostPay\Domain\Entity\ProductDelivery;
use Crehler\InpostPay\Domain\ValueObject\DeliveryType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\{LineItem, LineItemCollection};
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function array_key_exists;

class ProductDeliveryAvailabilityChecker
{
    /**
     * @var array<string, \Shopware\Core\Checkout\Shipping\ShippingMethodEntity|null>
     */
    private array $shippingMethodCache = [];

    public function __construct(
        private readonly DeliveryMappingProvider $deliveryMappingProvider,
        #[Autowire(service: 'shipping_method.repository')]
        private readonly EntityRepository $shippingMethodRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param DeliveryType[] $availableDeliveryTypes
     *
     * @return ProductDelivery[]|null null when product has no restrictions (object should be omitted)
     */
    public function check(
        Cart $cart,
        LineItem $lineItem,
        SalesChannelContext $context,
        array $availableDeliveryTypes,
    ): ?array {
        if ($availableDeliveryTypes === []) {
            return null;
        }

        $results = [];

        foreach ($availableDeliveryTypes as $type) {
            $shippingMethodId = $this->deliveryMappingProvider->getShippingMethodIdForDeliveryType(
                $type,
                $context->getSalesChannelId(),
            );

            if ($shippingMethodId === null) {
                $results[$type->value] = true;
                continue;
            }

            $shippingMethod = $this->loadShippingMethod($shippingMethodId, $context);
            if ($shippingMethod === null) {
                $results[$type->value] = true;
                continue;
            }

            $rule = $shippingMethod->getAvailabilityRule()?->getPayload();
            if ($rule === null) {
                $results[$type->value] = true;
                continue;
            }

            try {
                $miniCart = clone $cart;
                $miniCart->setLineItems(new LineItemCollection([$lineItem]));
                $available = $rule->match(new CartRuleScope($miniCart, $context));
            } catch (Throwable $e) {
                // Fallback to available — don't block payload on rule eval failure.
                $this->logger->warning(
                    '[InpostPay][ProductDeliveryAvailabilityChecker] rule eval failed',
                    [
                        'error' => $e->getMessage(),
                        'shippingMethodId' => $shippingMethodId,
                        'productId' => $lineItem->getReferencedId(),
                    ],
                );
                $available = true;
            }

            $results[$type->value] = $available;
        }

        $allTrue = true;
        foreach ($results as $value) {
            if ($value !== true) {
                $allTrue = false;
                break;
            }
        }

        if ($allTrue) {
            return null;
        }

        $deliveries = [];
        foreach ($availableDeliveryTypes as $type) {
            $deliveries[] = new ProductDelivery($type, $results[$type->value]);
        }

        return $deliveries;
    }

    private function loadShippingMethod(
        string $shippingMethodId,
        SalesChannelContext $context,
    ): ?\Shopware\Core\Checkout\Shipping\ShippingMethodEntity {
        if (array_key_exists($shippingMethodId, $this->shippingMethodCache)) {
            return $this->shippingMethodCache[$shippingMethodId];
        }

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('availabilityRule');

        $shippingMethod = $this->shippingMethodRepository
            ->search($criteria, $context->getContext())
            ->first();

        $this->shippingMethodCache[$shippingMethodId] = $shippingMethod;

        return $shippingMethod;
    }
}
