<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce uniqueness for active examples, including examples without a type target.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $duplicateExists = $this->connection->fetchOne(
            'SELECT 1 FROM example_table WHERE deleted_at IS NULL '
            . 'GROUP BY type, COALESCE(type_target_id, 0), lookup_group, lookup_key '
            . 'HAVING COUNT(*) > 1 LIMIT 1',
        );
        $this->abortIf(
            $duplicateExists !== false,
            'Active duplicate examples exist. Resolve them before this migration.',
        );

        $this->addSql(
            'ALTER TABLE example_table '
            . 'DROP INDEX example_data_unq, '
            . 'ADD normalized_type_target_id BIGINT UNSIGNED '
            . 'GENERATED ALWAYS AS (COALESCE(type_target_id, 0)) STORED, '
            . 'ADD active_uniqueness_key TINYINT UNSIGNED '
            . 'GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED, '
            . 'ADD UNIQUE INDEX example_active_data_unq '
            . '(type, normalized_type_target_id, lookup_group, lookup_key, active_uniqueness_key)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql(
            'ALTER TABLE example_table '
            . 'DROP INDEX example_active_data_unq, '
            . 'DROP active_uniqueness_key, '
            . 'DROP normalized_type_target_id, '
            . 'ADD UNIQUE INDEX example_data_unq '
            . '(type, type_target_id, lookup_group, lookup_key, deleted_at)',
        );
    }
}
