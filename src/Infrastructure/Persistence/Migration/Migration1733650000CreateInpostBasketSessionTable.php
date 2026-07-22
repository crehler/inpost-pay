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

class Migration1733650000CreateInpostBasketSessionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733650000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `inpost_basket_session` (
                `id` BINARY(16) NOT NULL,
                `basket_id` VARCHAR(255) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `inpost_basket_id` VARCHAR(255) NULL,
                `basket_binding_api_key` VARCHAR(500) NULL,
                `confirmation_response` JSON NULL,
                `order_id` BINARY(16) NULL,
                `bound_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.inpost_basket_session.basket_id` (`basket_id`),
                KEY `idx.inpost_basket_session.inpost_basket_id` (`inpost_basket_id`),
                KEY `idx.inpost_basket_session.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.inpost_basket_session.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    public function rollback(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `inpost_basket_session`');
    }
}
