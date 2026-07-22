<?php

declare(strict_types=1);

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

namespace Crehler\InpostPay\Infrastructure\Persistence\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds `cart_token` to decouple the Shopware cart token from the InPost-facing
 * `basket_id`.
 *
 * `basket_id` is the merchant-assigned identifier InPost remembers at bind time
 * and uses for every later call (get_basket, basket_event, confirmation). It must
 * stay immutable. The Shopware context token, however, changes on events like a
 * guest logging in (SalesChannelContextTokenChangeEvent), which previously
 * overwrote `basket_id` and broke InPost lookups (404 BASKET_NOT_FOUND).
 *
 * `cart_token` follows the live Shopware token; `basket_id` stays fixed.
 */
class Migration1733653000AddCartTokenColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733653000;
    }

    public function update(Connection $connection): void
    {
        $columnExists = (int) $connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inpost_basket_session'
              AND COLUMN_NAME = 'cart_token'
        ");

        if ($columnExists === 0) {
            $connection->executeStatement('
                ALTER TABLE `inpost_basket_session`
                ADD COLUMN `cart_token` VARCHAR(255) NULL AFTER `basket_id`
            ');
        }

        // Backfill existing rows: the current cart lives under the same token as
        // the basket_id until a context-token change moves it.
        $connection->executeStatement('
            UPDATE `inpost_basket_session`
            SET `cart_token` = `basket_id`
            WHERE `cart_token` IS NULL
        ');

        $indexExists = (int) $connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inpost_basket_session'
              AND INDEX_NAME = 'idx.inpost_basket_session.cart_token'
        ");

        if ($indexExists === 0) {
            $connection->executeStatement('
                CREATE INDEX `idx.inpost_basket_session.cart_token`
                ON `inpost_basket_session` (`cart_token`)
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
