<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261017010000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Clarify contact verification, claim expiry, failure type, and operator index names without changing stored values.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('ALTER TABLE contact_info RENAME TO contact_verification, RENAME INDEX contact_info_context_slot_unq TO contact_verification_context_slot_unq');
        $this->addSql('ALTER TABLE integration_event_outbox RENAME COLUMN claim_until TO claim_expires_at, RENAME COLUMN last_error TO last_failure_type');
        $this->addSql('ALTER TABLE integration_event_inbox RENAME COLUMN claimed_until TO claim_expires_at');
        $this->addSql('ALTER TABLE operator_action RENAME INDEX operator_bootstrap_unq TO operator_action_bootstrap_unq, RENAME INDEX operator_target_idx TO operator_action_target_idx');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('ALTER TABLE operator_action RENAME INDEX operator_action_bootstrap_unq TO operator_bootstrap_unq, RENAME INDEX operator_action_target_idx TO operator_target_idx');
        $this->addSql('ALTER TABLE integration_event_inbox RENAME COLUMN claim_expires_at TO claimed_until');
        $this->addSql('ALTER TABLE integration_event_outbox RENAME COLUMN claim_expires_at TO claim_until, RENAME COLUMN last_failure_type TO last_error');
        $this->addSql('ALTER TABLE contact_verification RENAME TO contact_info, RENAME INDEX contact_verification_context_slot_unq TO contact_info_context_slot_unq');
    }
}
