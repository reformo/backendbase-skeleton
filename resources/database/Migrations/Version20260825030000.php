<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bounded queue-delivery failure tracking.';
    }

    public function up(Schema $schema): void
    {
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
        $this->addSql('DROP TABLE integration_event_delivery_failure');
    }
}
