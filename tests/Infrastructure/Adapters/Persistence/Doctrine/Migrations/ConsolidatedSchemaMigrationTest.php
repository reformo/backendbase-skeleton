<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Migrations;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Backendbase\Migrations\Version20260823000000;
use Backendbase\Migrations\Version20260823215059;
use Backendbase\Migrations\Version20261014020000;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use Doctrine\Migrations\Query\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function array_map;
use function basename;
use function count;
use function file_get_contents;
use function glob;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ConsolidatedSchemaMigrationTest extends TestCase
{
    /**
     * @param class-string<BackendbaseAbstractMigration> $class
     * @param list<string>                               $tables
     */
    #[DataProvider('installations')]
    public function testEachInstallationCreatesItsFinalTables(string $class, array $tables): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $migration  = new $class($connection, new NullLogger());
        self::assertFalse($migration->isTransactional());
        self::assertNotSame('', $migration->getDescription());
        $migration->up(new Schema());
        $statements = array_map(static fn (Query $query): string => $query->getStatement(), $migration->getSql());
        self::assertCount(count($tables), $statements);
        foreach ($tables as $position => $table) {
            self::assertStringStartsWith('CREATE TABLE ' . $table . ' (', $statements[$position]);
        }
    }

    /** @return iterable<string, array{class-string<BackendbaseAbstractMigration>, list<string>}> */
    public static function installations(): iterable
    {
        yield 'messaging' => [Version20260823000000::class, ['integration_event_outbox', 'integration_event_inbox', 'integration_event_delivery_failure', 'external_effect_resolution', 'integration_event_replay']];
        yield 'example' => [Version20260823215059::class, ['example_table']];
        yield 'identity' => [Version20261014020000::class, ['identity_admin', 'identity_user', 'identity_challenge', 'identity_account', 'identity_operator_action']];
    }

    public function testReleasePinsTheLastOfTheThreeInstallations(): void
    {
        $files = glob('resources/database/Migrations/Version*.php');
        self::assertIsArray($files);
        self::assertSame(['Version20260823000000.php', 'Version20260823215059.php', 'Version20261014020000.php'], array_map(basename(...), $files));
        $source = file_get_contents('deployment/release.json');
        self::assertIsString($source);
        $release = json_decode($source, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($release);
        self::assertSame(Version20261014020000::class, $release['migrationTarget']);
        self::assertFalse($release['applicationRollbackSafe']);
    }

    /** @param class-string<BackendbaseAbstractMigration> $class */
    #[DataProvider('evidenceInstallations')]
    public function testRollbackCannotRemoveEvidence(string $class): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $migration  = new $class($connection, new NullLogger());
        $this->expectException(IrreversibleMigration::class);
        $migration->down(new Schema());
    }

    /** @return iterable<string, array{class-string<BackendbaseAbstractMigration>}> */
    public static function evidenceInstallations(): iterable
    {
        yield 'recovery' => [Version20260823000000::class];
        yield 'identity' => [Version20261014020000::class];
    }

    /** @return iterable<string, array{class-string<BackendbaseAbstractMigration>}> */
    public static function installationClasses(): iterable
    {
        foreach (self::installations() as $name => $definition) {
            yield $name => [$definition[0]];
        }
    }

    /** @param class-string<BackendbaseAbstractMigration> $class */
    #[DataProvider('installationClasses')]
    public function testInstallationRejectsOtherPlatforms(string $class): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $migration  = new $class($connection, new NullLogger());
        $this->expectException(AbortMigration::class);
        $migration->up(new Schema());
    }
}
