<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Persistence\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class InpostBasketSessionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'inpost_basket_session_collection';
    }

    protected function getExpectedClass(): string
    {
        return InpostBasketSessionEntity::class;
    }
}
