<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the transactional integration-event outbox.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE integration_event_outbox ('
            . 'id CHAR(36) NOT NULL, '
            . 'event_name VARCHAR(190) NOT NULL, '
            . 'payload JSON NOT NULL, '
            . 'occurred_at DATETIME(6) NOT NULL, '
            . 'created_at DATETIME(6) NOT NULL, '
            . 'available_at DATETIME(6) NOT NULL, '
            . 'published_at DATETIME(6) DEFAULT NULL, '
            . 'attempts INT UNSIGNED DEFAULT 0 NOT NULL, '
            . 'last_error VARCHAR(255) DEFAULT NULL, '
            . 'INDEX integration_event_outbox_pending_idx '
            . '(published_at, available_at, created_at), '
            . 'PRIMARY KEY (id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_event_outbox');
    }
}
