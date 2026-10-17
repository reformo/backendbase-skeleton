<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Mapping;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Schema\MappedTableFilter;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_match;

final class MappedTableFilterTest extends TestCase
{
    #[DataProvider('quotedNames')]
    public function testQuotedAdminNamesRemainInBothRuntimeAndMigrationDiffSchemas(string $name): void
    {
        $filter = new MappedTableFilter(['`admin`', 'account', 'contact_verification']);
        self::assertTrue($filter($name));
        self::assertSame(1, preg_match($filter->expression(), $name));
        self::assertTrue($filter(new Table('admin')));
        self::assertFalse($filter('identity_admin'));
        self::assertFalse($filter('operator_action'));
        self::assertFalse($filter('other_admin'));
    }

    /** @return iterable<string, array{string}> */
    public static function quotedNames(): iterable
    {
        yield 'plain' => ['admin'];
        yield 'MySQL quoting' => ['`admin`'];
        yield 'DBAL object name quoting' => ['"admin"'];
    }

    public function testMigrationMetadataIsVisibleOnlyWhenRequested(): void
    {
        $runtime    = new MappedTableFilter(['account']);
        $migrations = new MappedTableFilter(['account'], true);
        self::assertFalse($runtime('doctrine_migration_versions'));
        self::assertTrue($migrations('doctrine_migration_versions'));
        self::assertTrue($runtime('account'));
        self::assertTrue($migrations('account'));
    }

    public function testTableNamesAreMatchedAsLiteralIdentifiers(): void
    {
        $filter = new MappedTableFilter(['catalog.items']);
        self::assertTrue($filter('catalog.items'));
        self::assertFalse($filter('catalogXitems'));
    }
}
