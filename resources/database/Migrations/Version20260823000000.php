<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20260823000000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the integration-event outbox, inbox, and delivery-failure tables.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql(
            'CREATE TABLE integration_event_outbox ('
            . 'id CHAR(36) NOT NULL, '
            . 'event_name VARCHAR(190) NOT NULL, '
            . 'event_version VARCHAR(32) NOT NULL, '
            . 'payload JSON NOT NULL, '
            . 'occurred_at DATETIME(6) NOT NULL, '
            . 'created_at DATETIME(6) NOT NULL, '
            . 'available_at DATETIME(6) NOT NULL, '
            . 'published_at DATETIME(6) DEFAULT NULL, '
            . 'attempts INT UNSIGNED DEFAULT 0 NOT NULL, '
            . 'last_error VARCHAR(255) DEFAULT NULL, '
            . 'claim_token CHAR(36) DEFAULT NULL, '
            . 'claim_until DATETIME(6) DEFAULT NULL, '
            . 'INDEX integration_event_outbox_pending_idx '
            . '(published_at, available_at, claim_until, created_at), '
            . 'PRIMARY KEY (id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE integration_event_inbox ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id CHAR(36) NOT NULL, '
            . 'event_name VARCHAR(190) NOT NULL, '
            . 'received_at DATETIME(6) NOT NULL, '
            . 'processed_at DATETIME(6) DEFAULT NULL, '
            . 'claimed_until DATETIME(6) DEFAULT NULL, '
            . 'claim_token CHAR(36) DEFAULT NULL, '
            . 'INDEX integration_event_inbox_processed_idx (processed_at), '
            . 'INDEX integration_event_inbox_claim_idx (claimed_until), '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE integration_event_delivery_failure ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id CHAR(36) NOT NULL, '
            . 'attempts INT UNSIGNED NOT NULL, '
            . 'last_failure_type VARCHAR(190) NOT NULL, '
            . 'last_failed_at DATETIME(6) NOT NULL, '
            . 'dead_lettered_at DATETIME(6) DEFAULT NULL, '
            . 'INDEX integration_event_delivery_failure_dead_lettered_idx (dead_lettered_at), '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql('DROP TABLE integration_event_delivery_failure');
        $this->addSql('DROP TABLE integration_event_inbox');
        $this->addSql('DROP TABLE integration_event_outbox');
    }
}
