<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters;

use DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redislabs\Interfaces\RedisClientInterface;
use Redislabs\Module\RedisJson\RedisJsonInterface;

final class RedisDependencyDefinitionsTest extends TestCase
{
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
