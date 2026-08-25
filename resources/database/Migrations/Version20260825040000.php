<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add leases for external effects processed through the integration inbox.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE integration_event_inbox '
            . 'ADD claimed_until DATETIME(6) DEFAULT NULL, '
            . 'ADD claim_token CHAR(36) DEFAULT NULL, '
            . 'ADD INDEX integration_event_inbox_claim_idx (claimed_until)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE integration_event_inbox '
            . 'DROP INDEX integration_event_inbox_claim_idx, '
            . 'DROP claimed_until, '
            . 'DROP claim_token',
        );
    }
}
