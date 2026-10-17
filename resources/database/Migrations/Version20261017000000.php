<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261017000000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove bounded-context prefixes from identity tables and indexes while preserving stored rows.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('ALTER TABLE identity_account RENAME TO `account`, RENAME INDEX identity_account_active_email_unq TO account_active_email_unq, RENAME INDEX identity_account_active_mobile_unq TO account_active_mobile_unq');
        $this->addSql('ALTER TABLE identity_user RENAME TO `user`, RENAME INDEX identity_user_account_unq TO user_account_unq');
        $this->addSql('ALTER TABLE identity_admin RENAME TO `admin`, RENAME INDEX identity_admin_account_unq TO admin_account_unq');
        $this->addSql('ALTER TABLE identity_operator_action RENAME TO operator_action, RENAME INDEX identity_operator_bootstrap_unq TO operator_bootstrap_unq, RENAME INDEX identity_operator_target_idx TO operator_target_idx');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('ALTER TABLE operator_action RENAME TO identity_operator_action, RENAME INDEX operator_bootstrap_unq TO identity_operator_bootstrap_unq, RENAME INDEX operator_target_idx TO identity_operator_target_idx');
        $this->addSql('ALTER TABLE `admin` RENAME TO identity_admin, RENAME INDEX admin_account_unq TO identity_admin_account_unq');
        $this->addSql('ALTER TABLE `user` RENAME TO identity_user, RENAME INDEX user_account_unq TO identity_user_account_unq');
        $this->addSql('ALTER TABLE `account` RENAME TO identity_account, RENAME INDEX account_active_email_unq TO identity_account_active_email_unq, RENAME INDEX account_active_mobile_unq TO identity_account_active_mobile_unq');
    }
}
