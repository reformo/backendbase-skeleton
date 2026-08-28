<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use PhpAmqpLib\Connection\AbstractConnection;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        MessagePublisher::class => static function (ContainerInterface $container) {
            $queueSettings = $container->get(Settings::class)->get('queue');
            if (! is_array($queueSettings)) {
                throw new UnexpectedValueException('The queue settings are invalid.');
            }

            $driver = $queueSettings['driver'] ?? null;
            if ($driver === 'sqs') {
                return $container->get(SqsQueue::class);
            }

            if ($driver !== 'rabbitmq') {
                throw new UnexpectedValueException('The queue driver must be rabbitmq or sqs.');
            }

            $rabbitMQSettings = $container->get(Settings::class)->get('rabbitmq');
            if (! is_array($rabbitMQSettings)) {
                throw new UnexpectedValueException('The RabbitMQ settings are invalid.');
            }

            return new RabbitMQ($container->get(AbstractConnection::class), $rabbitMQSettings);
        },
        MessageConsumer::class => static fn (ContainerInterface $container): MessageConsumer => $container->get(
            MessagePublisher::class,
        ),
    ]);
};
