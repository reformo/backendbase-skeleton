<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Seeders\IdentityAndAccessPrivilegeSeeder;
use Backendbase\Shared\Migrations\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20260829010000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Add identity and access account tables and privileges.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS example_accounts ('
            . 'id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, '
            . 'uuid CHAR(36) NOT NULL, '
            . 'email VARCHAR(254) NOT NULL, '
            . 'password_hash VARCHAR(255) NOT NULL, '
            . 'created_at DATETIME(6) NOT NULL, '
            . 'deleted_at DATETIME(6) DEFAULT NULL, '
            . 'active_uniqueness_key TINYINT UNSIGNED '
            . 'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED, '
            . 'UNIQUE INDEX example_accounts_uuid_unq (uuid), '
            . 'UNIQUE INDEX example_accounts_active_email_unq (email, active_uniqueness_key), '
            . 'PRIMARY KEY (id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS example_privileges ('
            . 'id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, '
            . 'uuid CHAR(36) NOT NULL, '
            . 'title VARCHAR(160) NOT NULL, '
            . 'slug VARCHAR(100) NOT NULL, '
            . 'created_at DATETIME(6) NOT NULL, '
            . 'deleted_at DATETIME(6) DEFAULT NULL, '
            . 'UNIQUE INDEX example_privileges_uuid_unq (uuid), '
            . 'UNIQUE INDEX example_privileges_slug_unq (slug), '
            . 'PRIMARY KEY (id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS example_account_privileged ('
            . 'id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, '
            . 'uuid CHAR(36) NOT NULL, '
            . 'account_id BIGINT UNSIGNED NOT NULL, '
            . 'privilege_id BIGINT UNSIGNED NOT NULL, '
            . 'created_at DATETIME(6) NOT NULL, '
            . 'expired_at DATETIME(6) DEFAULT NULL, '
            . 'UNIQUE INDEX example_account_privileged_uuid_unq (uuid), '
            . 'UNIQUE INDEX example_account_privilege_unq (account_id, privilege_id), '
            . 'INDEX example_account_privileged_active_idx (account_id, expired_at), '
            . 'INDEX example_account_privileged_privilege_idx (privilege_id), '
            . 'PRIMARY KEY (id), '
            . 'CONSTRAINT example_account_privileged_account_fk '
            . 'FOREIGN KEY (account_id) REFERENCES example_accounts (id), '
            . 'CONSTRAINT example_account_privileged_privilege_fk '
            . 'FOREIGN KEY (privilege_id) REFERENCES example_privileges (id)'
            . ') DEFAULT CHARACTER SET utf8mb4',
        );

        (new IdentityAndAccessPrivilegeSeeder())->seed($this->connection);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql('DROP TABLE IF EXISTS example_account_privileged');
        $this->addSql('DROP TABLE IF EXISTS example_privileges');
        $this->addSql('DROP TABLE IF EXISTS example_accounts');
    }
}
