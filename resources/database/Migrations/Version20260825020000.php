<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add outbox event versions and short-lived relay claims.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE integration_event_outbox '
            . 'ADD event_version VARCHAR(32) DEFAULT NULL AFTER event_name, '
            . 'ADD claim_token CHAR(36) DEFAULT NULL, '
            . 'ADD claim_until DATETIME(6) DEFAULT NULL',
        );
        $this->addSql("UPDATE integration_event_outbox SET event_version = '1.0' WHERE event_version IS NULL");
        $this->addSql(
            'ALTER TABLE integration_event_outbox MODIFY event_version VARCHAR(32) NOT NULL',
        );
        $this->addSql('DROP INDEX integration_event_outbox_pending_idx ON integration_event_outbox');
        $this->addSql(
            'CREATE INDEX integration_event_outbox_pending_idx '
            . 'ON integration_event_outbox (published_at, available_at, claim_until, created_at)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX integration_event_outbox_pending_idx ON integration_event_outbox');
        $this->addSql(
            'CREATE INDEX integration_event_outbox_pending_idx '
            . 'ON integration_event_outbox (published_at, available_at, created_at)',
        );
        $this->addSql(
            'ALTER TABLE integration_event_outbox '
            . 'DROP event_version, DROP claim_token, DROP claim_until',
        );
    }
}
