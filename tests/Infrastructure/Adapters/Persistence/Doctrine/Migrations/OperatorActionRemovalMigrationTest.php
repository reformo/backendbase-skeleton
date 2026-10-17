<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Migrations;

use Backendbase\Migrations\Version20261017173407;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class OperatorActionRemovalMigrationTest extends TestCase
{
    public function testThePlanDropsOnlyTheOperatorActionTable(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $migration  = new Version20261017173407($connection, new NullLogger());
        $migration->up(new Schema());
        $queries = $migration->getSql();
        self::assertCount(1, $queries);
        self::assertSame('DROP TABLE operator_action', $queries[0]->getStatement());
        self::assertFalse($migration->isTransactional());
        self::assertNotSame('', $migration->getDescription());
        self::assertFalse($connection->isConnected());
    }

    public function testRemovalRejectsOtherPlatforms(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $migration  = new Version20261017173407($connection, new NullLogger());
        $this->expectException(AbortMigration::class);
        $migration->up(new Schema());
    }

    public function testRollbackCannotFabricateTheRemovedHistory(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $migration  = new Version20261017173407($connection, new NullLogger());
        $this->expectException(IrreversibleMigration::class);
        $migration->down(new Schema());
    }
}
