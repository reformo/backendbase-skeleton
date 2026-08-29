<?php

declare(strict_types=1);

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Primitives\PasswordHash;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Ramsey\Uuid\Uuid;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('BACKENDBASE_DAST_FIXTURE') !== 'true') {
    throw new RuntimeException('Set BACKENDBASE_DAST_FIXTURE=true for the ephemeral DAST fixture.');
}

$dsn      = getenv('BACKENDBASE_DB_DSN');
$email    = getenv('BACKENDBASE_DAST_ACCOUNT_EMAIL');
$password = getenv('BACKENDBASE_DAST_ACCOUNT_PASSWORD');

if (! is_string($dsn) || $dsn === '') {
    throw new RuntimeException('BACKENDBASE_DB_DSN is required.');
}

if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    throw new RuntimeException('BACKENDBASE_DAST_ACCOUNT_EMAIL must be a valid email address.');
}

if (! is_string($password) || $password === '') {
    throw new RuntimeException('BACKENDBASE_DAST_ACCOUNT_PASSWORD is required.');
}

$connection   = DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse($dsn));
$passwordHash = PasswordHash::fromPassword(new SensitiveParameterValue($password))->toString();

$connection->transactional(static function (Connection $connection) use ($email, $passwordHash): void {
    $now       = DateTimeImmutable::create()->format('Y-m-d H:i:s.u');
    $accountId = $connection->fetchOne(
        'SELECT id FROM example_accounts WHERE email = :email',
        ['email' => $email],
    );

    if ($accountId === false) {
        $connection->insert('example_accounts', [
            'uuid' => Uuid::uuid7()->toString(),
            'email' => $email,
            'password_hash' => $passwordHash,
            'created_at' => $now,
            'deleted_at' => null,
        ]);
        $accountId = $connection->lastInsertId();
    } else {
        $connection->update('example_accounts', [
            'password_hash' => $passwordHash,
            'deleted_at' => null,
        ], ['id' => $accountId]);
    }

    if (! is_string($accountId) && ! is_int($accountId)) {
        throw new RuntimeException('The DAST account could not be resolved.');
    }

    $privilegeIds = $connection->fetchFirstColumn(
        'SELECT id FROM example_privileges WHERE deleted_at IS NULL ORDER BY id ASC',
    );
    if ($privilegeIds === []) {
        throw new RuntimeException('No active privileges exist for the DAST account.');
    }

    foreach ($privilegeIds as $privilegeId) {
        $connection->executeStatement(
            'UPDATE example_account_privileged SET expired_at = NULL '
            . 'WHERE account_id = :accountId AND privilege_id = :privilegeId',
            ['accountId' => $accountId, 'privilegeId' => $privilegeId],
        );
        $grantExists = $connection->fetchOne(
            'SELECT id FROM example_account_privileged '
            . 'WHERE account_id = :accountId AND privilege_id = :privilegeId',
            ['accountId' => $accountId, 'privilegeId' => $privilegeId],
        );
        if ($grantExists !== false) {
            continue;
        }

        $connection->insert('example_account_privileged', [
            'uuid' => Uuid::uuid7()->toString(),
            'account_id' => $accountId,
            'privilege_id' => $privilegeId,
            'created_at' => $now,
            'expired_at' => null,
        ]);
    }
});
