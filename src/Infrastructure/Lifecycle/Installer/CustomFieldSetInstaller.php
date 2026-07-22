<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Lifecycle\Installer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsAnyFilter, EqualsFilter};
use Shopware\Core\System\CustomField\CustomFieldTypes;

use function array_column;
use function array_map;

class CustomFieldSetInstaller
{
    public const CUSTOM_FIELDSET_NAME = 'inpost_pay_order';

    private const CUSTOM_FIELDS = [
        [
            'name' => 'inpost_pay_payment_type',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Payment Type',
                    'de-DE' => 'Zahlungsart',
                    'pl-PL' => 'Typ platnosci',
                ],
                'customFieldPosition' => 1,
            ],
        ],
        [
            'name' => 'inpost_pay_basket_id',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Basket ID',
                    'de-DE' => 'Warenkorb-ID',
                    'pl-PL' => 'ID koszyka',
                ],
                'customFieldPosition' => 2,
            ],
        ],
        [
            'name' => 'inpost_pay_order_created_at',
            'type' => CustomFieldTypes::DATETIME,
            'config' => [
                'label' => [
                    'en-GB' => 'Order Created At (InPost)',
                    'de-DE' => 'Bestellung erstellt am (InPost)',
                    'pl-PL' => 'Data utworzenia zamowienia (InPost)',
                ],
                'customFieldPosition' => 3,
            ],
        ],
        [
            'name' => 'inpost_pay_delivery_codes',
            'type' => CustomFieldTypes::JSON,
            'config' => [
                'label' => [
                    'en-GB' => 'Delivery Codes',
                    'de-DE' => 'Liefercodes',
                    'pl-PL' => 'Kody dostawy',
                ],
                'customFieldPosition' => 4,
            ],
        ],
        [
            'name' => 'inpost_pay_services_cost_gross',
            'type' => CustomFieldTypes::FLOAT,
            'config' => [
                'label' => [
                    'en-GB' => 'Services Cost (Gross)',
                    'de-DE' => 'Servicekosten (Brutto)',
                    'pl-PL' => 'Koszt uslug (brutto)',
                ],
                'customFieldPosition' => 5,
            ],
        ],
        [
            'name' => 'inpost_pay_account_mail',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Account Email',
                    'de-DE' => 'Konto-E-Mail',
                    'pl-PL' => 'Email konta',
                ],
                'helpText' => [
                    'en-GB' => 'Email address of the InPost Pay account',
                    'pl-PL' => 'Adres email konta InPost Pay',
                ],
                'customFieldPosition' => 6,
            ],
        ],
        [
            'name' => 'inpost_pay_delivery_mail',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Delivery Email (Notification)',
                    'de-DE' => 'Liefer-E-Mail (Benachrichtigung)',
                    'pl-PL' => 'Email dostawy (powiadomienia)',
                ],
                'helpText' => [
                    'en-GB' => 'This email is used for all order notifications instead of customer account email',
                    'pl-PL' => 'Ten email jest uzywany do wszystkich powiadomien o zamowieniu zamiast emaila konta klienta',
                ],
                'customFieldPosition' => 7,
            ],
        ],
        [
            'name' => 'inpost_pay_payment_status',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Payment Status (Code)',
                    'de-DE' => 'Zahlungsstatus (Code)',
                    'pl-PL' => 'Status platnosci (kod)',
                ],
                'helpText' => [
                    'en-GB' => 'Technical payment status code from InPost Pay API',
                    'de-DE' => 'Technischer Zahlungsstatuscode von der InPost Pay API',
                    'pl-PL' => 'Techniczny kod statusu płatności z API InPost Pay',
                ],
                'customFieldPosition' => 8,
            ],
        ],
        [
            'name' => 'inpost_pay_payment_status_description',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Payment Status',
                    'de-DE' => 'Zahlungsstatus',
                    'pl-PL' => 'Status platnosci',
                ],
                'helpText' => [
                    'en-GB' => 'Human-readable payment status description',
                    'de-DE' => 'Lesbare Zahlungsstatusbeschreibung',
                    'pl-PL' => 'Czytelny opis statusu płatności',
                ],
                'customFieldPosition' => 9,
            ],
        ],
        [
            'name' => 'inpost_pay_payment_id',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Payment ID',
                    'de-DE' => 'Zahlungs-ID',
                    'pl-PL' => 'ID platnosci',
                ],
                'customFieldPosition' => 10,
            ],
        ],
        [
            'name' => 'inpost_pay_payment_reference',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'label' => [
                    'en-GB' => 'Payment Reference',
                    'de-DE' => 'Zahlungsreferenz',
                    'pl-PL' => 'Referencja platnosci',
                ],
                'customFieldPosition' => 11,
            ],
        ],
        [
            'name' => 'inpost_pay_payment_updated_at',
            'type' => CustomFieldTypes::DATETIME,
            'config' => [
                'label' => [
                    'en-GB' => 'Payment Updated At',
                    'de-DE' => 'Zahlung aktualisiert am',
                    'pl-PL' => 'Data aktualizacji platnosci',
                ],
                'customFieldPosition' => 12,
            ],
        ],
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository,
        private readonly EntityRepository $customFieldRepository,
    ) {
    }

    public function install(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        $existingId = $this->customFieldSetRepository->searchIds($criteria, $context)->firstId();

        $existingFieldIds = $this->getExistingCustomFieldIds($context);

        $customFields = array_map(static function (array $field) use ($existingFieldIds): array {
            if (isset($existingFieldIds[$field['name']])) {
                $field['id'] = $existingFieldIds[$field['name']];
            }

            return $field;
        }, self::CUSTOM_FIELDS);

        $data = [
            'name' => self::CUSTOM_FIELDSET_NAME,
            'config' => [
                'label' => [
                    'en-GB' => 'InPost Pay',
                    'de-DE' => 'InPost Pay',
                    'pl-PL' => 'InPost Pay',
                ],
            ],
            'customFields' => $customFields,
        ];

        if ($existingId !== null) {
            $data['id'] = $existingId;
        }

        $this->customFieldSetRepository->upsert([$data], $context);

        $this->addRelations($context);
    }

    public function uninstall(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        $id = $this->customFieldSetRepository->searchIds($criteria, $context)->firstId();

        if ($id !== null) {
            $this->customFieldSetRepository->delete([['id' => $id]], $context);
        }
    }

    /**
     * @return array<string, string> map of custom field name => id
     */
    private function getExistingCustomFieldIds(Context $context): array
    {
        $names = array_column(self::CUSTOM_FIELDS, 'name');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        $entities = $this->customFieldRepository->search($criteria, $context);

        $ids = [];
        foreach ($entities as $entity) {
            $ids[$entity->getName()] = $entity->getId();
        }

        return $ids;
    }

    private function addRelations(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        $id = $this->customFieldSetRepository->searchIds($criteria, $context)->firstId();

        if ($id === null) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFieldSetId', $id));
        $criteria->addFilter(new EqualsFilter('entityName', 'order'));

        $existingRelationId = $this->customFieldSetRelationRepository->searchIds($criteria, $context)->firstId();

        if ($existingRelationId !== null) {
            return;
        }

        $this->customFieldSetRelationRepository->upsert([
            [
                'customFieldSetId' => $id,
                'entityName' => 'order',
            ],
        ], $context);
    }
}
