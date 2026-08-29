<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ\PhpAmqpLibRabbitMQConnectionFactory;
use Backendbase\Infrastructure\Health\RabbitMQConnectionFactory;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        RabbitMQConnectionFactory::class => static function (ContainerInterface $container) {
            $settings          = $container->get(Settings::class);
            $rabbitMQSettings  = $settings->get('rabbitmq');
            $readinessSettings = $settings->get('readiness');
            $timeout           = (float) ($readinessSettings['timeoutSeconds'] ?? 2);
            $configuration     = new AMQPConnectionConfig();
            $configuration->setHost($rabbitMQSettings['host']);
            $configuration->setPort($rabbitMQSettings['port']);
            $configuration->setUser($rabbitMQSettings['user']);
            $configuration->setPassword($rabbitMQSettings['password']);
            $configuration->setVhost($rabbitMQSettings['vhost']);
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
            $rabbitMQSettings = $container->get(Settings::class)->get('rabbitmq');
            $configuration    = new AMQPConnectionConfig();
            $configuration->setHost($rabbitMQSettings['host']);
            $configuration->setPort($rabbitMQSettings['port']);
            $configuration->setUser($rabbitMQSettings['user']);
            $configuration->setPassword($rabbitMQSettings['password']);
            $configuration->setVhost($rabbitMQSettings['vhost']);
            $configuration->setConnectionTimeout($rabbitMQSettings['connectionTimeout']);
            $configuration->setReadTimeout($rabbitMQSettings['readWriteTimeout']);
            $configuration->setWriteTimeout($rabbitMQSettings['readWriteTimeout']);
            $configuration->setKeepalive(true);
            $configuration->setHeartbeat($rabbitMQSettings['heartbeat']);
            $configuration->setIsLazy(true);

            return AMQPConnectionFactory::create($configuration);
        },
    ]);
};
