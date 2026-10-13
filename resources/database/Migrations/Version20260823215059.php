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
        return 'Create the example table with active lookup identity uniqueness.';
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
            . 'id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, '
            . 'uuid CHAR(36) NOT NULL, '
            . 'type ENUM(\'system\', \'user\') NOT NULL, '
            . 'type_target_id BIGINT UNSIGNED DEFAULT NULL, '
            . 'normalized_type_target_id BIGINT UNSIGNED '
            . 'GENERATED ALWAYS AS (COALESCE(type_target_id, 0)) STORED, '
            . 'lookup_group VARCHAR(32) NOT NULL, '
            . 'lookup_key VARCHAR(160) NOT NULL, '
            . 'lookup_value VARCHAR(2048) NOT NULL, '
            . 'details JSON NOT NULL, '
            . 'is_active INT UNSIGNED DEFAULT 1 NOT NULL, '
            . 'updated_at DATETIME DEFAULT NULL, '
            . 'deleted_at DATETIME DEFAULT NULL, '
            . 'active_uniqueness_key TINYINT UNSIGNED '
            . 'GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED, '
            . 'created_at DATETIME NOT NULL, '
            . 'INDEX example_search_idx (type, type_target_id, lookup_group, lookup_key, is_active, deleted_at), '
            . 'INDEX example_relation_idx (type, type_target_id, lookup_key, deleted_at), '
            . 'UNIQUE INDEX example_active_data_unq '
            . '(type, normalized_type_target_id, lookup_group, lookup_key, active_uniqueness_key), '
            . 'UNIQUE INDEX example_uuid_unq (uuid), '
            . 'PRIMARY KEY (id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
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
