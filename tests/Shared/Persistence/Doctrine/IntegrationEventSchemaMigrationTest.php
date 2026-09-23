<?php

declare(strict_types=1);

namespace Tests\Shared\Persistence\Doctrine;

use Backendbase\Migrations\Version20260823000000;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Query\Query;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function array_map;
use function array_slice;
use function basename;
use function dirname;
use function file_get_contents;
use function glob;

final class IntegrationEventSchemaMigrationTest extends TestCase
{
    #[Test]
    public function itCreatesTheFinalMessagingSchemaBeforeOtherMigrations(): void
    {
        $migrationFiles = glob(dirname(__DIR__, 4) . '/resources/database/Migrations/Version*.php');
        self::assertIsArray($migrationFiles);
        self::assertSame('Version20260823000000.php', basename($migrationFiles[0]));
        foreach (array_slice($migrationFiles, 1) as $migrationFile) {
            $source = file_get_contents($migrationFile);
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/integration_event_/', $source);
        }

        $migration = $this->migration();
        self::assertFalse($migration->isTransactional());
        $migration->up(new Schema());
        $statements = array_map(static fn (Query $query): string => $query->getStatement(), $migration->getSql());
        self::assertCount(3, $statements);
        self::assertStringStartsWith('CREATE TABLE integration_event_outbox (', $statements[0]);
        self::assertStringStartsWith('CREATE TABLE integration_event_inbox (', $statements[1]);
        self::assertStringStartsWith('CREATE TABLE integration_event_delivery_failure (', $statements[2]);
        self::assertStringContainsString('event_version VARCHAR(32) NOT NULL', $statements[0]);
        self::assertStringContainsString('claim_token CHAR(36) DEFAULT NULL', $statements[0]);
        self::assertStringContainsString(
            'integration_event_outbox_pending_idx (published_at, available_at, claim_until, created_at)',
            $statements[0],
        );
        self::assertStringContainsString('integration_event_inbox_processed_idx (processed_at)', $statements[1]);
        self::assertStringContainsString('integration_event_inbox_claim_idx (claimed_until)', $statements[1]);
        self::assertStringContainsString(
            'integration_event_delivery_failure_dead_lettered_idx (dead_lettered_at)',
            $statements[2],
        );
    }

    private function migration(): Version20260823000000
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => '127.0.0.1',
            'serverVersion' => '8.4.0',
        ]);

        return new Version20260823000000($connection, new NullLogger());
    }
}
