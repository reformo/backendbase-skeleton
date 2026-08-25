<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the idempotent integration-event inbox.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE integration_event_inbox ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id CHAR(36) NOT NULL, '
            . 'event_name VARCHAR(190) NOT NULL, '
            . 'received_at DATETIME(6) NOT NULL, '
            . 'processed_at DATETIME(6) DEFAULT NULL, '
            . 'INDEX integration_event_inbox_processed_idx (processed_at), '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_event_inbox');
    }
}
