<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Provider;

use Crehler\InpostPay\Domain\ValueObject\Consent\{ConsentConfiguration, ConsentLinkType, ConsentRequirementType, ConsentTranslation};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use function is_array;

final readonly class ConsentConfigProvider
{
    /**
     * @var string
     */
    private const CONFIG_KEY = 'InpostPay.config.consents';
    /**
     * @var string
     */
    private const DEFAULT_LOCALE = 'pl-PL';

    public function __construct(
        private SystemConfigService $systemConfigService,
        private EntityRepository $languageRepository,
    ) {
    }

    /**
     * @return ConsentConfiguration[]
     */
    public function getEnabledConsents(SalesChannelContext $context): array
    {
        $consentsData = $this->systemConfigService->get(
            self::CONFIG_KEY,
            $context->getSalesChannel()->getId()
        );

        if (empty($consentsData) || !is_array($consentsData)) {
            return $this->getDefaultConsents();
        }

        $configurations = [];
        foreach ($consentsData as $consentData) {
            if (!is_array($consentData)) {
                continue;
            }

            if (!($consentData['enabled'] ?? false)) {
                continue;
            }

            $configurations[] = ConsentConfiguration::fromArray($consentData);
        }

        if (empty($configurations)) {
            return $this->getDefaultConsents();
        }

        return $configurations;
    }

    /**
     * @return ConsentConfiguration[]
     */
    public function getAllConsents(?string $salesChannelId = null): array
    {
        $consentsData = $this->systemConfigService->get(self::CONFIG_KEY, $salesChannelId);

        if (empty($consentsData) || !is_array($consentsData)) {
            return $this->getDefaultConsents();
        }

        $configurations = [];
        foreach ($consentsData as $consentData) {
            if (!is_array($consentData)) {
                continue;
            }

            $configurations[] = ConsentConfiguration::fromArray($consentData);
        }

        return $configurations;
    }

    public function getTranslationForLanguage(
        ConsentConfiguration $config,
        string $languageId,
        Context $context,
    ): ConsentTranslation {
        $locale = $this->resolveLocale($languageId, $context);

        return $config->getTranslation($locale, self::DEFAULT_LOCALE);
    }

    public function resolveLocale(string $languageId, Context $context): string
    {
        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        /** @var LanguageEntity|null $language */
        $language = $this->languageRepository->search($criteria, $context)->first();

        if ($language === null || $language->getLocale() === null) {
            return self::DEFAULT_LOCALE;
        }

        return $language->getLocale()->getCode();
    }

    /**
     * @return ConsentConfiguration[]
     */
    private function getDefaultConsents(): array
    {
        return [
            new ConsentConfiguration(
                id: 'terms',
                enabled: true,
                requirementType: ConsentRequirementType::REQUIRED_ONCE,
                version: '1.0',
                linkType: ConsentLinkType::EXTERNAL,
                cmsPageId: null,
                externalLink: null,
                translations: [
                    'pl-PL' => [
                        'description' => 'Akceptuję regulamin sklepu',
                        'labelLink' => 'Regulamin',
                    ],
                    'en-GB' => [
                        'description' => 'I accept the store terms and conditions',
                        'labelLink' => 'Terms and Conditions',
                    ],
                    'de-DE' => [
                        'description' => 'Ich akzeptiere die Allgemeinen Geschäftsbedingungen',
                        'labelLink' => 'AGB',
                    ],
                ],
            ),
        ];
    }
}
