<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20261017173407 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the operator action table; retain invitation and approval evidence in Admin state.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof AbstractMySQLPlatform, 'This migration supports MySQL only.');
        $this->addSql('DROP TABLE operator_action');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removed operator history requires a database backup and the matching application.');
    }
}
