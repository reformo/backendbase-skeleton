<?php

declare(strict_types=1);

use Backendbase\Infrastructure\UseCase\Console\GoodHousekeeping\ClearCache;
use Backendbase\Infrastructure\UseCase\Console\Queue;
use DI\ContainerBuilder;

use function DI\autowire;

return static function (ContainerBuilder $containerBuilder) {
    $commands = [
        Queue\ContainerAwareQueueConsumer::class => autowire(Queue\ContainerAwareQueueConsumer::class),
        Queue\CleanupIntegrationMessages::class => autowire(Queue\CleanupIntegrationMessages::class),
        Queue\NotifiyReciever::class => autowire(Queue\NotifiyReciever::class),
        Queue\RelayOutboxMessages::class => autowire(Queue\RelayOutboxMessages::class),
        Queue\ShowOutboxStatus::class => autowire(Queue\ShowOutboxStatus::class),
        ClearCache::class => autowire(ClearCache::class),

    ];

    $containerBuilder->addDefinitions($commands);

    return array_keys($commands);
};
