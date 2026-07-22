<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Subscriber;

use Crehler\InpostPay\Infrastructure\Facade\InpostPayFacade;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class InpostPaySubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    public const INPOSTPAY_CONFIG = 'inpostPayConfig';
    /**
     * @var string
     */
    private const SUPPORTED_CURRENCY = 'PLN';

    public function __construct(private readonly InpostPayFacade $inpostPayFacade)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        if ($context->getCurrency()->getIsoCode() !== self::SUPPORTED_CURRENCY) {
            $event->setParameter(self::INPOSTPAY_CONFIG, null);

            return;
        }

        $config = $this->inpostPayFacade->getWidgetConfig($context);

        $event->setParameter(self::INPOSTPAY_CONFIG, $config);
    }
}
