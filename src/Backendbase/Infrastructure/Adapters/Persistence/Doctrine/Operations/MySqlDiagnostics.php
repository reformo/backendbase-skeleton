<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Operations;

use Backendbase\Shared\Health\DatabaseDiagnostics;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use RuntimeException;

use function filter_var;
use function is_int;
use function is_string;
use function str_starts_with;

use const FILTER_VALIDATE_INT;

final readonly class MySqlDiagnostics implements DatabaseDiagnostics
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return array<string, int|string> */
    public function inspect(): array
    {
        $connection = $this->connection;
        if (! $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            throw new RuntimeException('Database diagnostics require the prepared MySQL 8.4 target.');
        }

        $environment = $connection->fetchAssociative('SELECT VERSION() AS serverVersion, @@character_set_database AS characterSet, @@collation_database AS collation');
        if ($environment === false || ! is_string($environment['serverVersion']) || ! str_starts_with($environment['serverVersion'], '8.4.')) {
            throw new RuntimeException('Database diagnostics require MySQL 8.4.');
        }

        $result  = [
            'serverVersion' => $environment['serverVersion'],
            'characterSet' => self::string($environment['characterSet']),
            'collation' => self::string($environment['collation']),
            'foreignKeys' => self::integer($connection->fetchOne('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()')),
        ];
        $metrics = $connection->fetchAllAssociative("SELECT NAME, COUNT AS metricCount FROM information_schema.INNODB_METRICS WHERE NAME IN ('lock_deadlocks', 'lock_timeouts', 'lock_row_lock_current_waits', 'lock_row_lock_time', 'buffer_pool_reads', 'buffer_pool_read_requests')");
        foreach ($metrics as $metric) {
            $name          = self::string($metric['NAME']);
            $result[$name] = self::integer($metric['metricCount']);
        }

        $sizes = $connection->fetchAssociative('SELECT COALESCE(SUM(DATA_LENGTH), 0) AS dataBytes, COALESCE(SUM(INDEX_LENGTH), 0) AS indexBytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        if ($sizes === false) {
            throw new RuntimeException('Database size metrics are unavailable.');
        }

        return [...$result, 'dataBytes' => self::integer($sizes['dataBytes']), 'indexBytes' => self::integer($sizes['indexBytes'])];
    }

    private static function string(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('The database diagnostic response is invalid.');
        }

        return $value;
    }

    private static function integer(mixed $value): int
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new RuntimeException('The database diagnostic response is invalid.');
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 0) {
            throw new RuntimeException('The database diagnostic response is invalid.');
        }

        return $integer;
    }
}
