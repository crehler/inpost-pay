<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Persistence\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\{EntityDefinition, FieldCollection};
use Shopware\Core\Framework\DataAbstractionLayer\Field\{DateTimeField, FkField, IdField, JsonField, StringField};
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{PrimaryKey, Required};
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class InpostBasketSessionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'inpost_basket_session';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return InpostBasketSessionCollection::class;
    }

    public function getEntityClass(): string
    {
        return InpostBasketSessionEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('basket_id', 'basketId'))->addFlags(new Required()),
            new StringField('cart_token', 'cartToken'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            new StringField('inpost_basket_id', 'inpostBasketId'),
            new StringField('basket_binding_api_key', 'basketBindingApiKey'),
            new JsonField('confirmation_response', 'confirmationResponse'),
            new IdField('order_id', 'orderId'),
            new StringField('analytics_client_id', 'analyticsClientId'),
            new StringField('analytics_gclid', 'analyticsGclid'),
            new StringField('analytics_fbclid', 'analyticsFbclid'),
            (new DateTimeField('bound_at', 'boundAt'))->addFlags(new Required()),
            new DateTimeField('updated_at', 'updatedAt'),
            new DateTimeField('created_at', 'createdAt'),
        ]);
    }
}
