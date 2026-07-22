<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Provider;

use Crehler\InpostPay\Application\Service\InpostBasketSessionService;
use Crehler\InpostPay\Domain\Enum\BindingPlace;
use Crehler\InpostPay\Domain\ValueObject\{HtmlStyles, WidgetConfig, WidgetDisplayConfig};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final readonly class InpostPayConfigProvider
{
    /**
     * @var string
     */
    private const SANDBOX_URL = 'https://sandbox-inpostpay-widget-v2.inpost.pl/inpostpay.widget.v2.js';
    /**
     * @var string
     */
    private const PRODUCTION_URL = 'https://inpostpay-widget-v2.inpost.pl/inpostpay.widget.v2.js';

    public function __construct(
        private SystemConfigService $systemConfigService,
        private InpostBasketSessionService $basketSessionService,
    ) {
    }

    public function getWidgetConfig(?SalesChannelContext $context = null): WidgetConfig
    {
        $salesChannelId = $context?->getSalesChannel()->getId();
        $displayConfigurations = $this->buildDisplayConfigurations($salesChannelId);

        $config = new WidgetConfig(
            scriptUrl: $this->getScriptUrl($salesChannelId),
            mode: $this->getMode($salesChannelId),
            clientId: $this->getClientId($salesChannelId),
            clientSecret: $this->getClientSecret($salesChannelId),
            postId: $this->getPostId($salesChannelId),
            merchantClientId: $this->getMerchantClientId($salesChannelId),
            basketBindingApiKey: '',
            merchantSecret: $this->getMerchantSecret($salesChannelId),
            displayConfigurations: $displayConfigurations,
        );

        if ($context === null) {
            return $config;
        }

        $basketId = $context->getToken();
        $session = $this->basketSessionService->getSessionByBasketId($basketId);

        if ($session === null) {
            return $config;
        }

        return new WidgetConfig(
            scriptUrl: $config->scriptUrl,
            mode: $config->mode,
            clientId: $config->clientId,
            clientSecret: $config->clientSecret,
            postId: $config->postId,
            merchantClientId: $config->merchantClientId,
            basketBindingApiKey: $session->getBasketBindingApiKey(),
            merchantSecret: $config->merchantSecret,
            displayConfigurations: $config->displayConfigurations,
        );
    }

    public function getActivePaymentMethods(SalesChannelContext $salesChannelContext): array
    {
        return $this->systemConfigService->get(
            key: 'InpostPay.config.enabledPaymentMethods',
            salesChannelId: $salesChannelContext->getSalesChannel()->getId()
        );
    }

    public function isExtendedLoggingEnabled(?string $salesChannelId = null): bool
    {
        return $this->getConfigBool('extendedLogging', $salesChannelId, false);
    }

    private function getMode(?string $salesChannelId = null): string
    {
        $mode = $this->systemConfigService->get('InpostPay.config.isSandbox', $salesChannelId);

        return $mode === true ? WidgetConfig::SANDBOX : WidgetConfig::PRODUCTION;
    }

    private function getScriptUrl(?string $salesChannelId = null): string
    {
        $mode = $this->getMode($salesChannelId);

        return match ($mode) {
            WidgetConfig::PRODUCTION => self::PRODUCTION_URL,
            WidgetConfig::SANDBOX => self::SANDBOX_URL,
        };
    }

    private function getMerchantClientId(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get('InpostPay.config.merchantClientId', $salesChannelId);
    }

    private function getClientId(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get('InpostPay.config.clientId', $salesChannelId);
    }

    private function getClientSecret(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get('InpostPay.config.clientSecret', $salesChannelId);
    }

    private function getPostId(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get('InpostPay.config.postId', $salesChannelId);
    }

    private function getMerchantSecret(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get('InpostPay.config.merchantSecret', $salesChannelId);
    }

    /**
     * @return array<string, WidgetDisplayConfig>
     */
    private function buildDisplayConfigurations(?string $salesChannelId = null): array
    {
        $configurations = [];

        foreach (BindingPlace::cases() as $bindingPlace) {
            $configurations[$bindingPlace->value] = $this->buildDisplayConfigForPlace(
                $bindingPlace,
                $salesChannelId
            );
        }

        return $configurations;
    }

    private function buildDisplayConfigForPlace(BindingPlace $bindingPlace, ?string $salesChannelId = null): WidgetDisplayConfig
    {
        $prefix = $bindingPlace->getConfigPrefix();

        return new WidgetDisplayConfig(
            bindingPlace: $bindingPlace,
            displayed: $this->getConfigBool($prefix . 'Displayed', $salesChannelId, true),
            darkMode: $this->getConfigBool($prefix . 'DarkMode', $salesChannelId, false),
            variant: $this->getConfigString($prefix . 'Variant', $salesChannelId) ?? WidgetDisplayConfig::VARIANT_SECONDARY,
            frameStyle: $this->getConfigString($prefix . 'FrameStyle', $salesChannelId),
            size: $this->getConfigString($prefix . 'Size', $salesChannelId),
            maxWidthPx: $this->getConfigInt($prefix . 'MaxWidth', $salesChannelId),
            htmlStyles: $this->buildHtmlStyles($prefix, $salesChannelId),
        );
    }

    private function buildHtmlStyles(string $prefix, ?string $salesChannelId = null): HtmlStyles
    {
        return new HtmlStyles(
            marginTop: $this->getConfigInt($prefix . 'MarginTop', $salesChannelId),
            marginBottom: $this->getConfigInt($prefix . 'MarginBottom', $salesChannelId),
            marginLeft: $this->getConfigInt($prefix . 'MarginLeft', $salesChannelId),
            marginRight: $this->getConfigInt($prefix . 'MarginRight', $salesChannelId),
            justifyContent: $this->getConfigString($prefix . 'JustifyContent', $salesChannelId),
        );
    }

    private function getConfigBool(string $key, ?string $salesChannelId = null, bool $default = false): bool
    {
        $value = $this->systemConfigService->get('InpostPay.config.' . $key, $salesChannelId);

        return $value !== null ? (bool) $value : $default;
    }

    private function getConfigString(string $key, ?string $salesChannelId = null): ?string
    {
        $value = $this->systemConfigService->get('InpostPay.config.' . $key, $salesChannelId);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function getConfigInt(string $key, ?string $salesChannelId = null): ?int
    {
        $value = $this->systemConfigService->get('InpostPay.config.' . $key, $salesChannelId);

        return $value !== null && $value !== '' ? (int) $value : null;
    }
}
