<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261014040000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Record local identity operator decisions and serialize one-time first-Admin setup.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('CREATE TABLE identity_operator_action (id BINARY(16) NOT NULL, uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, target_id BINARY(16) NOT NULL, action_name VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, bootstrap_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, operator_name VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, invitation_evidence VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, approval_evidence VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, recorded_at DATETIME(6) NOT NULL, UNIQUE INDEX identity_operator_bootstrap_unq (bootstrap_key), INDEX identity_operator_target_idx (target_id, recorded_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Identity operator evidence requires a reviewed forward migration.');
    }
}
