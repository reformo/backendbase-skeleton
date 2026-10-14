<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261014030000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Copy Example rows to assigned binary UUIDs and atomically retain the legacy table for forward recovery.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->validateExistingTable();
        $this->addSql('CREATE TABLE example_table_uuid (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, type ENUM(\'system\', \'user\') NOT NULL, type_target_id BIGINT UNSIGNED DEFAULT NULL, normalized_type_target_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(type_target_id, 0)) STORED, lookup_group VARCHAR(32) NOT NULL, lookup_key VARCHAR(160) NOT NULL, lookup_value VARCHAR(2048) NOT NULL, details JSON NOT NULL, is_active INT UNSIGNED DEFAULT 1 NOT NULL, updated_at DATETIME(6) DEFAULT NULL, deleted_at DATETIME(6) DEFAULT NULL, active_uniqueness_key TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED, created_at DATETIME(6) NOT NULL, INDEX example_search_idx (type, type_target_id, lookup_group, lookup_key, is_active, deleted_at), INDEX example_relation_idx (type, type_target_id, lookup_key, deleted_at), UNIQUE INDEX example_active_data_unq (type, normalized_type_target_id, lookup_group, lookup_key, active_uniqueness_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('LOCK TABLES example_table WRITE, example_table_uuid WRITE');
        $this->addSql('INSERT INTO example_table_uuid (id, type, type_target_id, lookup_group, lookup_key, lookup_value, details, is_active, updated_at, deleted_at, created_at) SELECT UUID_TO_BIN(uuid, 0), type, type_target_id, lookup_group, lookup_key, lookup_value, details, is_active, updated_at, deleted_at, created_at FROM example_table');
        $this->addSql('RENAME TABLE example_table TO example_table_legacy_20261014, example_table_uuid TO example_table');
        $this->addSql('UNLOCK TABLES');
    }

    private function validateExistingTable(): void
    {
        $type = $this->connection->fetchOne("SELECT data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'example_table' AND column_name = 'id'");
        if ($type === false) {
            return;
        }

        $this->abortIf($type !== 'bigint', 'Reconcile the completed Example conversion before retrying this migration.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_table WHERE uuid IS NULL OR uuid NOT REGEXP \'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$\' COLLATE utf8mb4_bin') !== 0, 'Every legacy public UUID must be canonical lowercase text.');
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM information_schema.key_column_usage WHERE referenced_table_schema = DATABASE() AND referenced_table_name = 'example_table'") !== 0, 'Resolve foreign-key references before replacing the Example primary key.');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Use the retained Example table in a reviewed forward recovery plan.');
    }
}
