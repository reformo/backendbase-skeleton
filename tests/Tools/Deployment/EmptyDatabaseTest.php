<?php

declare(strict_types=1);

namespace Tests\Tools\Deployment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedMySql;

use function getenv;
use function proc_close;
use function proc_open;
use function rawurlencode;
use function sprintf;

use const PHP_BINARY;

final class EmptyDatabaseTest extends TestCase
{
    #[DataProvider('schemas')]
    public function testFreshInstallationRejectsExistingSchemaObjects(string|null $statement, int $status): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $connection = $fixture->connection();
            if ($statement !== null) {
                $connection->executeStatement($statement);
            }

            self::assertSame($status, self::runGuard($connection));
        } finally {
            $fixture->destroy();
        }
    }

    /** @return iterable<string, array{string|null, int}> */
    public static function schemas(): iterable
    {
        yield 'empty' => [null, 0];
        yield 'table' => ['CREATE TABLE retained_data (id INT)', 255];
        yield 'view' => ['CREATE VIEW retained_view AS SELECT 1 AS id', 255];
        yield 'routine' => ['CREATE PROCEDURE retained_procedure() SELECT 1', 255];
        yield 'event' => ['CREATE EVENT retained_event ON SCHEDULE EVERY 1 DAY DO SELECT 1', 255];
    }

    private static function runGuard(Connection $connection): int
    {
        $parameters = $connection->getParams();
        $password   = $parameters['password'] ?? '';
        $database   = $connection->getDatabase();
        self::assertIsString($database);
        $dsn     = sprintf('mysql://root:%s@127.0.0.1:%d/%s', rawurlencode($password), $parameters['port'] ?? 3306, $database);
        $process = proc_open(
            [PHP_BINARY, 'bin/deployment/assert-empty-database.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            env_vars: [...getenv(), 'BACKENDBASE_DB_DSN' => $dsn],
        );
        self::assertIsResource($process);

        return proc_close($process);
    }
}
