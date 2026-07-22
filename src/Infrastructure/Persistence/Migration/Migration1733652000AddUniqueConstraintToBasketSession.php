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

class Migration1733652000AddUniqueConstraintToBasketSession extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733652000;
    }

    public function update(Connection $connection): void
    {
        $this->cleanupDuplicateSessions($connection);
        $this->addUniqueConstraint($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function cleanupDuplicateSessions(Connection $connection): void
    {
        $connection->executeStatement('
            DELETE FROM `inpost_basket_session`
            WHERE `confirmation_response` IS NULL
              AND `order_id` IS NULL
              AND `inpost_basket_id` IS NULL
              AND `basket_id` IN (
                  SELECT `basket_id` FROM (
                      SELECT `basket_id`
                      FROM `inpost_basket_session`
                      GROUP BY `basket_id`
                      HAVING COUNT(*) > 1
                  ) AS duplicates
              )
        ');

        $duplicates = $connection->fetchAllAssociative('
            SELECT `basket_id`, COUNT(*) - 1 AS `to_delete`
            FROM `inpost_basket_session`
            GROUP BY `basket_id`
            HAVING COUNT(*) > 1
        ');

        foreach ($duplicates as $row) {
            $connection->executeStatement('
                DELETE FROM `inpost_basket_session`
                WHERE `basket_id` = :basketId
                ORDER BY
                    CASE WHEN `confirmation_response` IS NULL THEN 0 ELSE 1 END ASC,
                    `created_at` ASC
                LIMIT :limit
            ', [
                'basketId' => $row['basket_id'],
                'limit' => (int) $row['to_delete'],
            ]);
        }
    }

    private function addUniqueConstraint(Connection $connection): void
    {
        $uniqueIndexExists = (int) $connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inpost_basket_session'
              AND INDEX_NAME = 'uniq.inpost_basket_session.basket_id'
        ");

        if ($uniqueIndexExists > 0) {
            return;
        }

        $oldIndexExists = (int) $connection->fetchOne("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inpost_basket_session'
              AND INDEX_NAME = 'idx.inpost_basket_session.basket_id'
        ");

        if ($oldIndexExists > 0) {
            $connection->executeStatement('
                DROP INDEX `idx.inpost_basket_session.basket_id` ON `inpost_basket_session`
            ');
        }

        $connection->executeStatement('
            CREATE UNIQUE INDEX `uniq.inpost_basket_session.basket_id` ON `inpost_basket_session` (`basket_id`)
        ');
    }
}
