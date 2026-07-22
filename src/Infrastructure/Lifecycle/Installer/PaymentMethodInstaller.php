<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Lifecycle\Installer;

use Crehler\InpostPay\Infrastructure\Lifecycle\Method\{InpostPayCodMethodData, InpostPayMethodData};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Uuid\Uuid;

class PaymentMethodInstaller
{
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly PluginIdProvider $pluginIdProvider,
        private readonly EntityRepository $ruleRepository,
        private readonly EntityRepository $currencyRepository,
    ) {
    }

    public function install(Context $context): void
    {
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass('Crehler\InpostPay\InpostPay', $context);

        foreach ($this->getMethodDataList() as $methodData) {
            $this->installMethod($methodData, $pluginId, $context);
        }
    }

    public function activate(Context $context): void
    {
        $this->updateActiveFlag(true, $context);
    }

    public function deactivate(Context $context): void
    {
        $this->updateActiveFlag(false, $context);
    }

    public function uninstall(Context $context): void
    {
        $this->deactivate($context);
    }

    /**
     * @return InpostPayMethodData[]
     */
    private function getMethodDataList(): array
    {
        return [new InpostPayMethodData(), new InpostPayCodMethodData()];
    }

    private function installMethod(InpostPayMethodData $methodData, string $pluginId, Context $context): void
    {
        $translations = $methodData->getTranslations();
        $defaultTranslation = $translations['en-GB'];

        $existingMethodId = $this->getExistingMethodId($methodData, $context);

        $paymentMethodData = [
            'id' => $existingMethodId ?? Uuid::randomHex(),
            'technicalName' => $methodData->getTechnicalName(),
            'handlerIdentifier' => $methodData->getHandler(),
            'name' => $defaultTranslation['name'],
            'position' => $methodData->getPosition(),
            'afterOrderEnabled' => true,
            'pluginId' => $pluginId,
            'description' => $defaultTranslation['description'] ?? '',
        ];

        $translationData = [
            'id' => $paymentMethodData['id'],
            'translations' => [
                'de-DE' => [
                    'name' => $translations['de-DE']['name'],
                    'description' => $translations['de-DE']['description'],
                ],
                'en-GB' => [
                    'name' => $translations['en-GB']['name'],
                    'description' => $translations['en-GB']['description'],
                ],
            ],
        ];

        if (isset($translations['pl-PL'])) {
            $translationData['translations']['pl-PL'] = [
                'name' => $translations['pl-PL']['name'],
                'description' => $translations['pl-PL']['description'],
            ];
        }

        $this->paymentMethodRepository->upsert([$paymentMethodData], $context);

        $this->paymentMethodRepository->upsert([$translationData], $context);

        $this->createPlnAvailabilityRule($paymentMethodData['id'], $context);
    }

    private function updateActiveFlag(bool $active, Context $context): void
    {
        $updates = [];

        foreach ($this->getMethodDataList() as $methodData) {
            $methodId = $this->getExistingMethodId($methodData, $context);

            if ($methodId) {
                $updates[] = [
                    'id' => $methodId,
                    'active' => $active,
                ];
            }
        }

        if ($updates !== []) {
            $this->paymentMethodRepository->update($updates, $context);
        }
    }

    private function getExistingMethodId(InpostPayMethodData $methodData, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $methodData->getHandler()));

        return $this->paymentMethodRepository->searchIds($criteria, $context)->firstId();
    }

    private function createPlnAvailabilityRule(string $paymentMethodId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', 'PLN'));
        $plnCurrencyId = $this->currencyRepository->searchIds($criteria, $context)->firstId();

        if (!$plnCurrencyId) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'InPost Pay - PLN Only'));
        $existingRuleId = $this->ruleRepository->searchIds($criteria, $context)->firstId();

        $ruleId = $existingRuleId ?? Uuid::randomHex();

        $this->ruleRepository->upsert([
            [
                'id' => $ruleId,
                'name' => 'InPost Pay - PLN Only',
                'priority' => 1,
                'conditions' => [
                    [
                        'type' => 'currency',
                        'value' => [
                            'operator' => '=',
                            'currencyIds' => [$plnCurrencyId],
                        ],
                    ],
                ],
            ],
        ], $context);

        $this->paymentMethodRepository->update([
            [
                'id' => $paymentMethodId,
                'availabilityRuleId' => $ruleId,
            ],
        ], $context);
    }
}
