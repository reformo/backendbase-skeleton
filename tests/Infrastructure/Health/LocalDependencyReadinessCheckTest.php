<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Health;

use Backendbase\Infrastructure\Health\MySQLReadinessCheck;
use Backendbase\Infrastructure\Health\RabbitMQReadinessCheck;
use Backendbase\Infrastructure\Health\RedisReadinessCheck;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use UnexpectedValueException;

final class LocalDependencyReadinessCheckTest extends TestCase
{
    #[Test]
    public function itChecksMySQLAndRedis(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::once())->method('fetchOne')->willReturn(1);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeQuery')->with('SELECT 1')->willReturn($result);

        $redisJson = $this->createMock(RedisJsonInterface::class);
        $redisJson->expects(self::once())->method('raw')->with('PING')->willReturn('PONG');

        $mySQLCheck = new MySQLReadinessCheck($connection);
        $redisCheck = new RedisReadinessCheck($redisJson);

        self::assertSame('mysql', $mySQLCheck->name());
        self::assertSame('redis', $redisCheck->name());
        $mySQLCheck->check();
        $redisCheck->check();
    }

    #[Test]
    public function itRejectsInvalidMySQLAndRedisResponses(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchOne')->willReturn(false);
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($result);
        $redisJson = $this->createStub(RedisJsonInterface::class);
        $redisJson->method('raw')->willReturn(false);

        $this->assertCheckFails(static fn () => new MySQLReadinessCheck($connection)->check());
        $this->assertCheckFails(static fn () => new RedisReadinessCheck($redisJson)->check());
    }

    #[Test]
    public function itChecksRabbitMQWithABoundedConnection(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects(self::exactly(2))->method('is_open')->willReturn(true);
        $channel->expects(self::once())->method('close');
        $connection = $this->createMock(AbstractConnection::class);
        $connection->expects(self::once())->method('channel')->willReturn($channel);
        $connection->expects(self::once())->method('isConnected')->willReturn(true);
        $connection->expects(self::once())->method('close');

        $check = new RabbitMQReadinessCheck(self::rabbitMQSettings(), static fn () => $connection);

        self::assertSame('queue', $check->name());
        $check->check();
    }

    #[Test]
    public function itRejectsAClosedRabbitMQChannel(): void
    {
        $channel = $this->createStub(AMQPChannel::class);
        $channel->method('is_open')->willReturn(false);
        $connection = $this->createStub(AbstractConnection::class);
        $connection->method('channel')->willReturn($channel);
        $connection->method('isConnected')->willReturn(false);

        $this->assertCheckFails(
            static fn () => new RabbitMQReadinessCheck(
                self::rabbitMQSettings(),
                static fn () => $connection,
            )->check(),
        );
    }

    private function assertCheckFails(callable $check): void
    {
        try {
            $check();
            self::fail('An invalid readiness response must fail.');
        } catch (UnexpectedValueException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array<string, mixed> */
    private static function rabbitMQSettings(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 5672,
            'user' => 'backendbase',
            'password' => 'backendbase',
            'vhost' => '/',
            'readinessTimeoutSeconds' => 2,
        ];
    }
}
