<?php

declare(strict_types=1);

namespace Tests\Tools\Services;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;
use Tests\Support\Services\RedisAuthentication;

final class RedisAuthenticationTest extends TestCase
{
    #[DataProvider('passwords')]
    public function testAuthenticationModeDoesNotChangeAssertionTotals(string $password): void
    {
        $client = $this->createStub(Redis::class);
        $calls  = [];
        $client->method('auth')->willReturnCallback(static function (string $received) use (&$calls): bool {
            $calls[] = $received;

            return true;
        });
        $before = self::getCount();
        RedisAuthentication::verify($client, $password);
        $assertions = self::getCount() - $before;
        self::assertSame(1, $assertions);
        self::assertSame($password === '' ? [] : [$password], $calls);
    }

    public function testRejectedCredentialsFailTheFixture(): void
    {
        $client = $this->createStub(Redis::class);
        $client->method('auth')->willReturn(false);
        $this->expectException(AssertionFailedError::class);
        RedisAuthentication::verify($client, 'test-only-password');
    }

    public function testAuthenticationErrorsReachTheCaller(): void
    {
        $client = $this->createStub(Redis::class);
        $client->method('auth')->willThrowException(new RedisException('Authentication unavailable.'));
        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('Authentication unavailable.');
        RedisAuthentication::verify($client, 'test-only-password');
    }

    /** @return iterable<string, array{string}> */
    public static function passwords(): iterable
    {
        yield 'without authentication' => [''];
        yield 'with authentication' => ['test-only-password'];
    }
}
