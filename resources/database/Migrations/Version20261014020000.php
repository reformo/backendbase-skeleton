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
        return 'Create identity, authentication challenge, and operator evidence tables.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('CREATE TABLE identity_admin (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, account_id BINARY(16) NOT NULL, normalized_login_name VARCHAR(64) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, created_at DATETIME(6) NOT NULL, deleted_at DATETIME(6) DEFAULT NULL, active_login_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_login_name ELSE NULL END) VIRTUAL, state JSON NOT NULL, UNIQUE INDEX identity_admin_account_unq (account_id), UNIQUE INDEX identity_admin_active_login_unq (active_login_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE identity_user (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, account_id BINARY(16) NOT NULL, normalized_login_name VARCHAR(64) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, created_at DATETIME(6) NOT NULL, deleted_at DATETIME(6) DEFAULT NULL, active_login_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_login_name ELSE NULL END) VIRTUAL, state JSON NOT NULL, UNIQUE INDEX identity_user_account_unq (account_id), UNIQUE INDEX identity_user_active_login_unq (active_login_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE identity_challenge (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, account_id BINARY(16) NOT NULL, actor_id BINARY(16) NOT NULL, realm VARCHAR(16) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, purpose VARCHAR(32) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, expires_at DATETIME(6) NOT NULL, state JSON NOT NULL, INDEX identity_challenge_account_idx (account_id, expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE identity_account (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INT DEFAULT 1 NOT NULL, normalized_email VARCHAR(254) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`, created_at DATETIME(6) NOT NULL, deleted_at DATETIME(6) DEFAULT NULL, active_email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_email ELSE NULL END) VIRTUAL, state JSON NOT NULL, credentials JSON NOT NULL, UNIQUE INDEX identity_account_active_email_unq (active_email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_tr_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE identity_operator_action (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, target_id BINARY(16) NOT NULL, action_name VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, bootstrap_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, operator_name VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, invitation_evidence VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, approval_evidence VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, recorded_at DATETIME(6) NOT NULL, UNIQUE INDEX identity_operator_bootstrap_unq (bootstrap_key), INDEX identity_operator_target_idx (target_id, recorded_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Identity, challenge, and operator history require a reviewed forward migration.');
    }
}
