<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ\PhpAmqpLibRabbitMQConnectionFactory;
use Backendbase\Infrastructure\Configuration\QueueSettings;
use Backendbase\Infrastructure\Health\RabbitMQConnectionFactory;
use DI\ContainerBuilder;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        RabbitMQConnectionFactory::class => static function (ContainerInterface $container) {
            $queueSettings    = $container->get(QueueSettings::class);
            $rabbitMQSettings = $queueSettings->rabbitMqConnection();
            $timeout          = $queueSettings->readinessTimeoutSeconds();
            $configuration    = new AMQPConnectionConfig();
            $host             = $rabbitMQSettings->host();
            $port             = $rabbitMQSettings->port();
            $user             = $rabbitMQSettings->user();
            $password         = $rabbitMQSettings->password();
            $virtualHost      = $rabbitMQSettings->virtualHost();
            $configuration->setHost($host);
            $configuration->setPort($port);
            $configuration->setUser($user);
            $configuration->setPassword($password);
            $configuration->setVhost($virtualHost);
            $configuration->setConnectionTimeout($timeout);
            $configuration->setReadTimeout($timeout);
            $configuration->setWriteTimeout($timeout);
            $configuration->setChannelRPCTimeout($timeout);
            $configuration->setKeepalive(false);
            $configuration->setHeartbeat(0);
            $configuration->setIsLazy(true);

            return new PhpAmqpLibRabbitMQConnectionFactory($configuration);
        },
        AbstractConnection::class => static function (ContainerInterface $container) {
            $queueSettings     = $container->get(QueueSettings::class);
            $rabbitMQSettings  = $queueSettings->rabbitMqRuntimeConnection();
            $connection        = $rabbitMQSettings->connection();
            $configuration     = new AMQPConnectionConfig();
            $host              = $connection->host();
            $port              = $connection->port();
            $user              = $connection->user();
            $password          = $connection->password();
            $virtualHost       = $connection->virtualHost();
            $connectionTimeout = $rabbitMQSettings->connectionTimeoutSeconds();
            $readWriteTimeout  = $rabbitMQSettings->readWriteTimeoutSeconds();
            $heartbeat         = $rabbitMQSettings->heartbeatSeconds();
            $configuration->setHost($host);
            $configuration->setPort($port);
            $configuration->setUser($user);
            $configuration->setPassword($password);
            $configuration->setVhost($virtualHost);
            $configuration->setConnectionTimeout($connectionTimeout);
            $configuration->setReadTimeout($readWriteTimeout);
            $configuration->setWriteTimeout($readWriteTimeout);
            $configuration->setKeepalive(true);
            $configuration->setHeartbeat($heartbeat);
            $configuration->setIsLazy(true);

            return AMQPConnectionFactory::create($configuration);
        },
    ]);
};
