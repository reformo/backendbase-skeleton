<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261014020000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Create identity and contact verification tables.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('CREATE TABLE identity_admin (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, account_id BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL, deleted_at DATETIME(6) DEFAULT NULL, state JSON NOT NULL, UNIQUE INDEX identity_admin_account_unq (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE identity_user (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, account_id BINARY(16) NOT NULL, created_at DATETIME(6) NOT NULL, deleted_at DATETIME(6) DEFAULT NULL, state JSON NOT NULL, UNIQUE INDEX identity_user_account_unq (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_info (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, context_hash VARCHAR(64) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, realm VARCHAR(16) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, purpose VARCHAR(40) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, contact_type VARCHAR(8) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, normalized_value VARCHAR(254) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, account_id BINARY(16) DEFAULT NULL, actor_id BINARY(16) DEFAULT NULL, verified_at DATETIME(6) DEFAULT NULL, state JSON NOT NULL, UNIQUE INDEX contact_info_context_slot_unq (context_hash, realm, purpose, contact_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE identity_account (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, normalized_email VARCHAR(254) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, normalized_mobile VARCHAR(16) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, first_name VARCHAR(160) DEFAULT NULL, last_name VARCHAR(160) DEFAULT NULL, created_at DATETIME(6) NOT NULL, deleted_at DATETIME(6) DEFAULT NULL, active_email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_email ELSE NULL END) VIRTUAL, active_mobile VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_mobile ELSE NULL END) VIRTUAL, state JSON NOT NULL, password_hash VARCHAR(255) NOT NULL, UNIQUE INDEX identity_account_active_email_unq (active_email), UNIQUE INDEX identity_account_active_mobile_unq (active_mobile), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Identity and contact verification history require a reviewed forward migration.');
    }
}
