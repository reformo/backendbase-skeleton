<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Security;

use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

final class SessionRedisTest extends TestCase
{
    public function testConnectionIsLazyAndTheConnectedClientIsReused(): void
    {
        $client      = $this->createStub(Redis::class);
        $connections = 0;
        $connection  = new SessionRedis(static function () use ($client, &$connections): Redis {
            $connections++;

            return $client;
        });
        self::assertSame(0, $connections);
        self::assertSame($client, $connection->client());
        $connection->client();
        self::assertSame(1, $connections);
    }

    public function testFailedConnectionsAreNotCached(): void
    {
        $client     = $this->createStub(Redis::class);
        $attempts   = 0;
        $failure    = new RedisException('Connection failed.');
        $connection = new SessionRedis(static function () use ($client, $failure, &$attempts): Redis {
            $attempts++;
            if ($attempts === 1) {
                throw $failure;
            }

            return $client;
        });
        try {
            $connection->client();
            self::fail('The connection failure must reach the caller.');
        } catch (RedisException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame($client, $connection->client());
        $connection->client();
        self::assertSame(2, $attempts);
    }
}
