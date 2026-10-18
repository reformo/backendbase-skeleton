<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Idempotency;

use Backendbase\Migrations\Version20261018000000;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ReplayMigrationTest extends TestCase
{
    public function testOfflinePlanCreatesOnlyTheReplayTableWithBothAccessPaths(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $migration  = new Version20261018000000($connection, new NullLogger());
        $migration->up(new Schema());
        $queries = $migration->getSql();
        self::assertCount(1, $queries);
        $sql = $queries[0]->getStatement();
        self::assertStringStartsWith('CREATE TABLE http_request_replay', $sql);
        self::assertStringContainsString('PRIMARY KEY (request_hash)', $sql);
        self::assertStringContainsString('INDEX http_request_replay_expiry_idx (expires_at)', $sql);
        self::assertStringContainsString('response MEDIUMTEXT CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL', $sql);
        self::assertStringNotContainsString('IF NOT EXISTS', $sql);
        self::assertFalse($connection->isConnected());
        self::assertFalse($migration->isTransactional());
        self::assertNotSame('', $migration->getDescription());
        $this->expectException(IrreversibleMigration::class);
        $migration->down(new Schema());
    }

    public function testTheMigrationRejectsOtherPlatforms(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $migration  = new Version20261018000000($connection, new NullLogger());
        $this->expectException(AbortMigration::class);
        $migration->up(new Schema());
    }
}
