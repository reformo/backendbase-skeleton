<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Infrastructure\Configuration\Queue\QueueDriver;
use Backendbase\Infrastructure\Configuration\QueueSettings;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\MessagePublisher;
use DI\ContainerBuilder;
use PhpAmqpLib\Connection\AbstractConnection;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        MessagePublisher::class => static function (ContainerInterface $container) {
            $queueSettings = $container->get(QueueSettings::class);
            if ($queueSettings->driver() === QueueDriver::SQS) {
                return $container->get(SqsQueue::class);
            }

            $connection = $container->get(AbstractConnection::class);
            $topology   = $queueSettings->rabbitMqTopology();

            return new RabbitMQ($connection, $topology);
        },
        MessageConsumer::class => static fn (ContainerInterface $container): MessageConsumer => $container->get(
            MessagePublisher::class,
        ),
    ]);
};
