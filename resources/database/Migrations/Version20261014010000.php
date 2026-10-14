<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261014010000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Record stable outbox destinations and audited message recovery decisions.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('ALTER TABLE integration_event_outbox ADD destination VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL');
        $this->addSql(
            'CREATE TABLE external_effect_resolution ('
            . 'id BINARY(16) NOT NULL, '
            . 'uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, '
            . 'consumer_name VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'message_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'decision VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'operator_name VARCHAR(255) NOT NULL, evidence_reference VARCHAR(255) NOT NULL, '
            . 'resolved_at DATETIME(6) NOT NULL, PRIMARY KEY(id), '
            . 'INDEX external_effect_resolution_message_idx (consumer_name, message_id)'
            . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci',
        );
        $this->addSql(
            'CREATE TABLE integration_event_replay ('
            . 'id BINARY(16) NOT NULL, '
            . 'uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, '
            . 'consumer_name VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'message_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'operator_name VARCHAR(255) NOT NULL, evidence_reference VARCHAR(255) NOT NULL, '
            . 'requested_at DATETIME(6) NOT NULL, PRIMARY KEY(id), '
            . 'INDEX integration_event_replay_message_idx (consumer_name, message_id)'
            . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci',
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Recovery evidence must remain available. Use a reviewed forward migration.');
    }
}
