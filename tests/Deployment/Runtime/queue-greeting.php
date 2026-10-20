<?php

declare(strict_types=1);

use Backendbase\Domain\ExampleCatalog\Contracts\Command\QueueGreeting;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use DI\ContainerBuilder;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

if (getenv('BACKENDBASE_DEPLOYMENT_REHEARSAL') !== '1') {
    throw new RuntimeException('The isolated deployment rehearsal is required.');
}

chdir('/srv/backendbase/webroot/api');
require 'vendor/autoload.php';

$builder       = new ContainerBuilder();
$configuration = new ConfigAggregator([new PhpFileProvider('config/autoload/*')]);
$settings      = require 'config/settings.php';
$settings($builder, $configuration->getMergedConfig());
$dependencies = require 'config/dependencies.php';
$dependencies($builder);
$container     = $builder->build();
$commandBus    = $container->get(CommandBus::class);
$accessControl = new class implements AccessControl {
    public function isAllowed(string $privilege, string|null $role = null): bool
    {
        if ($privilege !== 'example.add') {
            throw new RuntimeException('Unexpected fixture privilege.');
        }

        return true;
    }
};
$commandBus->handle(new QueueGreeting('Deployment Fixture', $accessControl));
