<?php

declare(strict_types=1);

use Backendbase\Domain\IdentityAndAccess\Adapters\Console\IdentityOperator;
use Backendbase\Infrastructure\Adapters\Console\GoodHousekeeping\ClearCache;
use Backendbase\Infrastructure\Adapters\Console\Operations\InspectDatabase;
use Backendbase\Infrastructure\Adapters\Console\Operations\SeedAdmin;
use Backendbase\Infrastructure\Adapters\Console\Queue;
use DI\ContainerBuilder;

use function DI\autowire;

return static function (ContainerBuilder $containerBuilder) {
    $commands = [
        IdentityOperator::class => autowire(IdentityOperator::class),
        InspectDatabase::class => autowire(InspectDatabase::class),
        SeedAdmin::class => autowire(SeedAdmin::class),
        Queue\ContainerAwareQueueConsumer::class => autowire(Queue\ContainerAwareQueueConsumer::class),
        Queue\CleanupIntegrationMessages::class => autowire(Queue\CleanupIntegrationMessages::class),
        Queue\RelayOutboxMessages::class => autowire(Queue\RelayOutboxMessages::class),
        Queue\ShowOutboxStatus::class => autowire(Queue\ShowOutboxStatus::class),
        Queue\ResolveExternalEffect::class => autowire(Queue\ResolveExternalEffect::class),
        Queue\ReplayOutboxMessage::class => autowire(Queue\ReplayOutboxMessage::class),
        ClearCache::class => autowire(ClearCache::class),

    ];

    $containerBuilder->addDefinitions($commands);

    return array_keys($commands);
};
