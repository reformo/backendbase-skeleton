<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261018000000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Store durable HTTP idempotency claims and encrypted responses with indexed expiry.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->platform;
        $this->abortIf(! $platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql(<<<'SQL'
CREATE TABLE http_request_replay (
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    response MEDIUMTEXT CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) DEFAULT NULL,
    PRIMARY KEY (request_hash),
    INDEX http_request_replay_expiry_idx (expires_at)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Retain request claims and responses to prevent duplicate effects.');
    }
}
