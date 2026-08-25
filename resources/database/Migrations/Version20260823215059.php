<?php

declare(strict_types=1);

namespace Backendbase\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260823215059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE example_table (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL, type ENUM(\'system\', \'user\') NOT NULL, type_target_id BIGINT UNSIGNED DEFAULT NULL, lookup_group VARCHAR(32) NOT NULL, lookup_key VARCHAR(160) NOT NULL, lookup_value VARCHAR(2048) NOT NULL, details JSON NOT NULL, is_active INT UNSIGNED DEFAULT 1 NOT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX example_search_idx (type, type_target_id, lookup_group, lookup_key, is_active, deleted_at), INDEX example_relation_idx (type, type_target_id, lookup_key, deleted_at), UNIQUE INDEX example_data_unq (type, type_target_id, lookup_group, lookup_key, deleted_at), UNIQUE INDEX example_uuid_unq (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE example_table');
    }
}
