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

class Migration1733651000AddAnalyticsColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733651000;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative(
            'SHOW COLUMNS FROM `inpost_basket_session` LIKE :column',
            ['column' => 'analytics_client_id']
        );

        if (empty($columns)) {
            $connection->executeStatement('
                ALTER TABLE `inpost_basket_session`
                ADD COLUMN `analytics_client_id` VARCHAR(255) NULL,
                ADD COLUMN `analytics_gclid` VARCHAR(512) NULL,
                ADD COLUMN `analytics_fbclid` VARCHAR(512) NULL
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
