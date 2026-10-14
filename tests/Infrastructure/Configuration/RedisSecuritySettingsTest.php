<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\RedisSettings;
use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function str_repeat;

final class RedisSecuritySettingsTest extends TestCase
{
    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidConnections')]
    public function testInvalidConnectionAndCredentialValuesAreRejected(array $changes): void
    {
        $this->expectException(UnexpectedValueException::class);
        ValidatedApplicationSettings::redis(['host' => '127.0.0.1', 'port' => 6379, ...$changes]);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConnections(): iterable
    {
        yield 'empty host' => [['host' => '']];
        yield 'host whitespace' => [['host' => 'bad host']];
        yield 'port range' => [['port' => 65536]];
        yield 'TLS type' => [['tls' => 'true']];
        yield 'username type' => [['username' => 1]];
        yield 'username without password' => [['username' => 'user']];
        yield 'password null byte' => [['password' => "secret\0value"]];
        yield 'password too long' => [['password' => str_repeat('a', 1025)]];
    }

    public function testAuthenticatedTlsConnectionsKeepTypedCredentialForms(): void
    {
        $root     = ['redis' => ['host' => 'redis.example.com', 'port' => 6379, 'password' => 'test-password', 'tls' => true], 'readiness' => ['timeoutSeconds' => 2]];
        $password = new RedisSettings(new Settings($root));
        self::assertSame('tls://redis.example.com', $password->connectionHost());
        self::assertSame('test-password', $password->credentials());
        $acl = new RedisSettings(new Settings([...$root, 'redis' => [...$root['redis'], 'username' => 'application']]));
        self::assertSame(['application', 'test-password'], $acl->credentials());
    }
}
