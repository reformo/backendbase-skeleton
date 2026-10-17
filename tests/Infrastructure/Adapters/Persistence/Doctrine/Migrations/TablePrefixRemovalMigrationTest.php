<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Migrations;

use Backendbase\Migrations\Version20261017000000;
use Backendbase\Migrations\Version20261017010000;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function array_reverse;
use function str_replace;

final class TablePrefixRemovalMigrationTest extends TestCase
{
    #[DataProvider('directions')]
    public function testPlanRenamesOnlyTheThreeTablesAndTheirIndexes(string $direction): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $migration  = new Version20261017000000($connection, new NullLogger());
        $migration->$direction(new Schema());
        $queries = $migration->getSql();
        $names   = ['account' => ['account_active_email_unq', 'account_active_mobile_unq'], 'user' => ['user_account_unq'], 'admin' => ['admin_account_unq']];
        if ($direction === 'down') {
            $names = array_reverse($names, true);
        }

        self::assertCount(3, $queries);
        $position = 0;
        foreach ($names as $name => $indexes) {
            $sql = str_replace('`', '', $queries[$position++]->getStatement());
            $this->assertRename($sql, $name, $indexes, $direction);
        }

        self::assertFalse($migration->isTransactional());
        self::assertNotSame('', $migration->getDescription());
        // Plan generation also works before the installation migrations have executed.
        self::assertFalse($connection->isConnected());
    }

    /** @param list<string> $indexes */
    private function assertRename(string $sql, string $table, array $indexes, string $direction): void
    {
        $sourcePrefix = $direction === 'up' ? 'identity_' : '';
        $targetPrefix = $direction === 'up' ? '' : 'identity_';
        $expected     = 'ALTER TABLE ' . $sourcePrefix . $table . ' RENAME TO ' . $targetPrefix . $table;
        foreach ($indexes as $index) {
            $expected .= ', RENAME INDEX ' . $sourcePrefix . $index . ' TO ' . $targetPrefix . $index;
        }

        self::assertSame($expected, $sql);
    }

    #[DataProvider('directions')]
    public function testRemainingNamingChangesAffectOnlyContactAndMessageTables(string $direction): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $migration  = new Version20261017010000($connection, new NullLogger());
        $migration->$direction(new Schema());
        $queries = $migration->getSql();
        $tables  = $direction === 'up'
            ? ['contact_info', 'integration_event_outbox', 'integration_event_inbox']
            : ['integration_event_inbox', 'integration_event_outbox', 'contact_verification'];
        self::assertCount(3, $queries);
        foreach ($tables as $position => $table) {
            self::assertStringStartsWith('ALTER TABLE ' . $table . ' ', $queries[$position]->getStatement());
        }
    }

    #[DataProvider('directions')]
    public function testRenameRejectsUnsupportedPlatforms(string $direction): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $migration  = new Version20261017000000($connection, new NullLogger());
        $this->expectException(AbortMigration::class);
        $migration->$direction(new Schema());
    }

    /** @return iterable<string, array{string}> */
    public static function directions(): iterable
    {
        yield 'forward' => ['up'];
        yield 'reverse' => ['down'];
    }
}
