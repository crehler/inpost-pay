<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Application\Service;

use Crehler\InpostPay\Domain\ValueObject\Consent\BasketConsent;
use Crehler\InpostPay\Infrastructure\Provider\ConsentConfigProvider;
use Crehler\InpostPay\Infrastructure\Service\ConsentLinkResolver;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

use function array_map;

final readonly class ConsentService
{
    public function __construct(
        private ConsentConfigProvider $configProvider,
        private ConsentLinkResolver $linkResolver,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return BasketConsent[]
     */
    public function getConsentsForBasket(SalesChannelContext $context): array
    {
        $configurations = $this->configProvider->getEnabledConsents($context);
        $consents = [];

        foreach ($configurations as $config) {
            $translation = $this->configProvider->getTranslationForLanguage(
                $config,
                $context->getLanguageId(),
                $context->getContext()
            );

            $link = $this->linkResolver->resolveLink($config, $context);

            if ($link === '') {
                $this->logger->warning('Consent has empty link - configure consent links in admin panel', [
                    'consentId' => $config->id,
                    'linkType' => $config->linkType->value,
                    'salesChannelId' => $context->getSalesChannelId(),
                ]);
            }

            $consents[] = new BasketConsent(
                consentId: $config->id,
                consentLink: $link,
                consentDescription: $translation->description,
                consentVersion: $config->version,
                requirementType: $config->requirementType,
                labelLink: $translation->labelLink,
            );
        }

        return $consents;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getConsentsArrayForBasket(SalesChannelContext $context): array
    {
        $consents = $this->getConsentsForBasket($context);

        return array_map(
            static fn (BasketConsent $consent) => $consent->toArray(),
            $consents
        );
    }
}
