<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters;

use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redis;
use Redislabs\Interfaces\RedisClientInterface;
use Redislabs\Module\RedisJson\RedisJsonInterface;

final class RedisDependencyDefinitionsTest extends TestCase
{
    public function testTheJsonLibraryReusesTheLazyNativeConnection(): void
    {
        $client      = $this->createMock(Redis::class);
        $expectation = $client->expects(self::once());
        $expectation->method('rawCommand');
        $expectation->with('PING');
        $expectation->willReturn('PONG');
        $connections = 0;
        $connection  = new SessionRedis(static function () use ($client, &$connections): Redis {
            $connections++;

            return $client;
        });
        $builder     = new ContainerBuilder();
        $provider    = require 'config/dependencies/redis.php';
        $provider($builder);
        $builder->addDefinitions([SessionRedis::class => $connection]);
        $container = $builder->build();
        $json      = $container->get(RedisJsonInterface::class);
        self::assertSame(0, $connections);
        self::assertSame('PONG', $json->raw('PING'));
        self::assertSame($client, $connection->client());
        self::assertSame(1, $connections);
    }

    #[Test]
    public function itResolvesTheClientOnlyWhenTheFirstRedisCommandRuns(): void
    {
        $redisClient = $this->createMock(RedisClientInterface::class);
        $redisClient->expects(self::once())
            ->method('rawCommand')
            ->with('PING', [])
            ->willReturn('PONG');
        $clientResolutionCount = 0;

        $containerBuilder = new ContainerBuilder();
        $redisProvider    = require 'config/dependencies/redis.php';
        $redisProvider($containerBuilder);
        $containerBuilder->addDefinitions([
            RedisClientInterface::class => static function () use (
                $redisClient,
                &$clientResolutionCount,
            ): RedisClientInterface {
                ++$clientResolutionCount;

                return $redisClient;
            },
        ]);

        $redisJson = $containerBuilder->build()->get(RedisJsonInterface::class);

        self::assertSame(0, $clientResolutionCount);
        self::assertSame('PONG', $redisJson->raw('PING'));
        self::assertSame(1, $clientResolutionCount);
    }
}
