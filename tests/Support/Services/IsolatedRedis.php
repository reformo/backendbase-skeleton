<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Redis;
use Redislabs\Module\RedisJson\RedisJson;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function filter_var;
use function getenv;
use function is_array;

use const FILTER_VALIDATE_INT;

final readonly class IsolatedRedis
{
    private function __construct(private Redis $client, private string $accountId)
    {
    }

    public static function create(string $accountId): self
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            TestCase::markTestSkipped('An explicitly prepared Redis 8 target is required.');
        }

        return new self(self::connect(), $accountId);
    }

    public function client(): Redis
    {
        return $this->client;
    }

    public function json(): RedisJsonInterface
    {
        return RedisJson::createWithPhpRedis($this->client);
    }

    /** @return array{string, string, string} */
    public function keys(Principal $principal): array
    {
        $identity = $principal->identity();
        Assert::assertSame($this->accountId, $identity['accountId']);
        $realm  = $principal->realm();
        $prefix = 'identity:{' . $realm->value . ':' . $this->accountId . '}:';

        return [$prefix . 'jti:' . $identity['jti'], $prefix . 'sid:' . $identity['sid'], $prefix . 'blocked'];
    }

    public function destroy(): void
    {
        $client = self::connect();
        foreach (['USER', 'ADMIN'] as $realm) {
            $cursor = null;
            do {
                $keys = $client->scan($cursor, 'identity:{' . $realm . ':' . $this->accountId . '}:*', 100);
                if (! is_array($keys) || $keys === []) {
                    continue;
                }

                $client->del($keys);
            } while ($cursor !== 0);
        }

        $client->close();
    }

    private static function connect(): Redis
    {
        $port = filter_var(getenv('BACKENDBASE_TEST_REDIS_PORT') ?: '6379', FILTER_VALIDATE_INT);
        Assert::assertIsInt($port);
        $password = getenv('BACKENDBASE_TEST_REDIS_PASSWORD');
        Assert::assertIsString($password);
        $client = new Redis();
        Assert::assertTrue($client->connect('127.0.0.1', $port, 2, read_timeout: 2));
        if ($password !== '') {
            Assert::assertTrue($client->auth($password));
        }

        $info = $client->info('server');
        Assert::assertIsArray($info);
        Assert::assertIsString($info['redis_version']);
        Assert::assertStringStartsWith('8.', $info['redis_version']);

        return $client;
    }
}
