<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20260823215059 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the final Example binary UUID schema and required query indexes.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->platform;
        $this->abortIf(
            ! $platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql(
            'CREATE TABLE example_table ('
            . 'id BINARY(16) NOT NULL, '
            . 'uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, '
            . 'version INT DEFAULT 1 NOT NULL, '
            . 'type ENUM(\'system\', \'user\') NOT NULL, '
            . 'type_target_id BIGINT UNSIGNED DEFAULT NULL, '
            . 'normalized_type_target_id BIGINT UNSIGNED '
            . 'GENERATED ALWAYS AS (COALESCE(type_target_id, 0)) STORED, '
            . 'lookup_group VARCHAR(32) NOT NULL, '
            . 'lookup_key VARCHAR(160) NOT NULL, '
            . 'lookup_value VARCHAR(2048) NOT NULL, '
            . 'details JSON NOT NULL, '
            . 'is_active INT UNSIGNED DEFAULT 1 NOT NULL, '
            . 'updated_at DATETIME(6) DEFAULT NULL, '
            . 'deleted_at DATETIME(6) DEFAULT NULL, '
            . 'active_uniqueness_key TINYINT UNSIGNED '
            . 'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED, '
            . 'created_at DATETIME(6) NOT NULL, '
            . 'INDEX example_search_idx (type, type_target_id, lookup_group, lookup_key, is_active, deleted_at), '
            . 'INDEX example_relation_idx (type, type_target_id, lookup_key, deleted_at), '
            . 'UNIQUE INDEX example_active_data_unq '
            . '(type, normalized_type_target_id, lookup_group, lookup_key, active_uniqueness_key), '
            . 'INDEX example_groups_idx (type, type_target_id, deleted_at, lookup_group), '
            . 'INDEX example_group_entries_idx (type, type_target_id, lookup_group, deleted_at, created_at, id), '
            . 'PRIMARY KEY (id)'
            . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci ENGINE = InnoDB',
        );
    }

    public function down(Schema $schema): void
    {
        $platform = $this->platform;
        $this->abortIf(
            ! $platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql('DROP TABLE example_table');
    }
}
