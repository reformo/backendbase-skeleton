<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;

use function array_slice;
use function fgets;
use function fwrite;
use function trim;

use const STDIN;
use const STDOUT;

final class IdentityConcurrencyWork
{
    /** @param list<string> $identities */
    public static function run(Connection $connection, string $operation, array $identities): void
    {
        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 10');
        fwrite(STDOUT, $connection->fetchOne('SELECT CONNECTION_ID()') . "\n");
        if ($operation === 'deadlock') {
            self::deadlock($connection, $identities);

            return;
        }

        if ($operation === 'register-contact' || $operation === 'register-contact-expired') {
            ContactConcurrencyWork::run($connection, $operation, $identities);

            return;
        }

        throw new RuntimeException('Unknown MySQL test operation.');
    }

    /** @param list<string> $identities */
    private static function deadlock(Connection $connection, array $identities): void
    {
        $connection->beginTransaction();
        $target = $identities[0];
        foreach (array_slice($identities, 1) as $id) {
            $connection->executeStatement('UPDATE identity_account SET version = version + 1 WHERE id = UUID_TO_BIN(?, 0)', [$id]);
        }

        fwrite(STDOUT, "locked\n");
        self::command('continue');
        $connection->executeStatement('UPDATE identity_account SET version = version + 1 WHERE id = UUID_TO_BIN(?, 0)', [$target]);
        $connection->commit();
        fwrite(STDOUT, "committed\n");
    }

    private static function command(string $expected): void
    {
        $line = fgets(STDIN);
        if ($line === false || trim($line) !== $expected) {
            throw new RuntimeException('The MySQL test worker received an invalid command.');
        }
    }
}
