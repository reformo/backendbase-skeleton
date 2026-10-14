<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use Backendbase\Infrastructure\Configuration\RedisSettings;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\HealthCheckData;
use DI\ContainerBuilder;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use Psr\Container\ContainerInterface;
use Redislabs\Interfaces\RedisClientInterface;
use Redislabs\Module\RedisJson\RedisJson;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function DI\create;
use function DI\get;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        SessionRedis::class => static function (ContainerInterface $container): SessionRedis {
            $settings    = $container->get(RedisSettings::class);
            $host        = $settings->connectionHost();
            $port        = $settings->port();
            $timeout     = $settings->readinessTimeoutSeconds();
            $credentials = $settings->credentials();

            return new SessionRedis(static function () use ($host, $port, $timeout, $credentials): Redis {
                $client = new Redis();
                $client->connect($host, $port, $timeout, context: ['stream' => ['verify_peer' => true, 'verify_peer_name' => true]]);
                $client->setOption(Redis::OPT_READ_TIMEOUT, $timeout);
                if ($credentials !== null) {
                    if (! $client->auth($credentials)) {
                        throw new RuntimeException('Redis authentication failed.');
                    }
                }

                $info = $client->info('server');
                if (! is_array($info) || ! is_string($info['redis_version'] ?? null) || ! str_starts_with($info['redis_version'], '8.')) {
                    throw new RuntimeException('Authentication requires Redis 8.');
                }

                return $client;
            });
        },
        RedisClientInterface::class => static function (ContainerInterface $container): RedisClientInterface {
            $redisSettings = $container->get(RedisSettings::class);
            try {
                $redisClient = new Redis();
                $host        = $redisSettings->connectionHost();
                $port        = $redisSettings->port();
                $timeout     = $redisSettings->readinessTimeoutSeconds();
                $redisClient->connect(
                    $host,
                    $port,
                    $timeout,
                    context: ['stream' => ['verify_peer' => true, 'verify_peer_name' => true]],
                );
                $redisClient->setOption(Redis::OPT_READ_TIMEOUT, $timeout);
                $credentials = $redisSettings->credentials();
                if ($credentials !== null) {
                    if (! $redisClient->auth($credentials)) {
                        throw new RuntimeException('Redis authentication failed.');
                    }
                }
            } catch (Throwable) {
                $healthStatus = new HealthCheckData();
                $healthStatus->setStatus(503);
                $healthStatus->setStatusString('rcon');

                throw ResourceNotFound::create('rcon', $healthStatus->jsonSerialize());
            }

            return new \Redislabs\RedisClient\Redis($redisClient);
        },
        RedisJsonInterface::class => create(RedisJson::class)
            ->constructor(get(RedisClientInterface::class))
            ->lazy(),
        Clock::class => SystemClock::fromUTC(),
    ]);
};
