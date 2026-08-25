<?php

declare(strict_types=1);

use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
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
