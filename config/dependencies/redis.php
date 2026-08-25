<?php

declare(strict_types=1);

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\HealthCheckData;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Redislabs\Module\RedisJson\RedisJson;
use Redislabs\Module\RedisJson\RedisJsonInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        RedisJsonInterface::class => static function (ContainerInterface $container) {
            $settings        = $container->get(Settings::class);
            $redisConfig     = $settings->get('redis');
            $readinessConfig = $settings->get('readiness');
            $timeoutSeconds  = (float) ($readinessConfig['timeoutSeconds'] ?? 2);
            try {
                $redisClient = new Redis();
                $redisClient->connect(
                    $redisConfig['host'] ?? '127.0.0.1',
                    $redisConfig['port'] ?? 6379,
                    $timeoutSeconds,
                );
                $redisClient->setOption(Redis::OPT_READ_TIMEOUT, $timeoutSeconds);
            } catch (Throwable) {
                $healthStatus = new HealthCheckData();
                $healthStatus->setStatus(503);
                $healthStatus->setStatusString('rcon');

                throw ResourceNotFound::create('rcon', $healthStatus->jsonSerialize());
            }

            return new RedisJson(new \Redislabs\RedisClient\Redis($redisClient));
        },
        Jwt::class => static function (ContainerInterface $container) {
            $settings = $container->get(Settings::class);

            return new Jwt($container->get(RedisJsonInterface::class), $settings->get('jwt'));
        },
    ]);
};
