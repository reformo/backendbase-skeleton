<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Configuration\DatabaseSettings;
use Backendbase\Shared\Services\Settings;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Dotenv\Dotenv;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

$projectRoot = dirname(__DIR__, 2);
chdir($projectRoot);
require 'vendor/autoload.php';

$dotenv = Dotenv::createUnsafeImmutable($projectRoot);
$dotenv->safeLoad();
$configuration = new ConfigAggregator([new PhpFileProvider('config/autoload/*')]);
$settings      = new Settings($configuration->getMergedConfig());
$database      = new DatabaseSettings($settings);
$parser        = new DsnParser(['mysql' => 'pdo_mysql']);
$parameters    = $parser->parse($database->dsn());
if (($parameters['driver'] ?? null) !== 'pdo_mysql' || empty($parameters['dbname'])) {
    throw new RuntimeException('Fresh installation requires an identified MySQL database.');
}

$parameters['driverOptions'][PDO::ATTR_TIMEOUT] = $database->connectionTimeoutSeconds();
$connection                                     = DriverManager::getConnection($parameters);
foreach (['TABLES' => 'TABLE_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $catalog => $column) {
    $count = $connection->fetchOne('SELECT COUNT(*) FROM information_schema.' . $catalog . ' WHERE ' . $column . ' = DATABASE()');
    if ($count !== 0 && $count !== '0') {
        throw new RuntimeException('Fresh installation refuses an existing schema. A reviewed conversion plan is required.');
    }
}

$connection->close();
echo "The selected database has no tables, views, routines, or events.\n";
