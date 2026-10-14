<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedMySql;

use function getenv;

final class MySqlCollationTest extends TestCase
{
    #[DataProvider('comparisons')]
    public function testDatabaseDefaultsAndUniqueKeysFollowTurkishUcaRules(string $first, string $second, bool $equal): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $connection = $fixture->connection();
            $connection->executeStatement('CREATE TABLE collation_probe (value VARCHAR(100) NOT NULL, UNIQUE KEY probe_value_unq (value)) ENGINE=InnoDB');
            self::assertSame('utf8mb4', $connection->fetchOne('SELECT @@character_set_database'));
            self::assertSame('utf8mb4_tr_0900_ai_ci', $connection->fetchOne('SELECT @@collation_database'));
            self::assertSame('utf8mb4_tr_0900_ai_ci', $connection->fetchOne("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'collation_probe' AND COLUMN_NAME = 'value'"));
            self::assertSame('NO PAD', $connection->fetchOne("SELECT PAD_ATTRIBUTE FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_tr_0900_ai_ci'"));
            $connection->insert('collation_probe', ['value' => $first]);
            try {
                $connection->insert('collation_probe', ['value' => $second]);
                self::assertFalse($equal, 'Equivalent values must conflict in the native unique index.');
                self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM collation_probe'));
            } catch (UniqueConstraintViolationException) {
                self::assertTrue($equal, 'Distinct values must remain separate under the selected collation.');
                self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM collation_probe'));
            }
        } finally {
            $fixture->destroy();
        }
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function comparisons(): iterable
    {
        yield 'dotless case pair' => ['I', 'ı', true];
        yield 'dotted case pair' => ['İ', 'i', true];
        yield 'distinct I groups' => ['I', 'i', false];
        yield 'distinct dotted capital' => ['ı', 'İ', false];
        yield 'accent insensitive' => ['e', 'é', true];
        yield 'Unicode composition' => ['é', "e\u{0301}", true];
        yield 'NO PAD trailing space' => ['key', 'key ', false];
    }
}
