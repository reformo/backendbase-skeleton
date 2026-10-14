<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Tests\Support\Services\IdentityConcurrencyWork;

require 'tests/bootstrap.php';
require 'config/doctrine-types.php';

stream_set_timeout(STDIN, 15);
$line = fgets(STDIN);
if ($line === false) {
    throw new RuntimeException('The MySQL test worker configuration is missing.');
}

/** @var array{parameters: array{driver: 'pdo_mysql', host: string, port: int, user: string, password: string, dbname: string, charset: string}, operation: string, identities: list<string>} $input */
$input      = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
$connection = DriverManager::getConnection($input['parameters']);
try {
    IdentityConcurrencyWork::run($connection, $input['operation'], $input['identities']);
} catch (Throwable $exception) {
    fwrite(STDOUT, 'failed:' . $exception::class . "\n");
    exit(1);
} finally {
    $connection->close();
}
