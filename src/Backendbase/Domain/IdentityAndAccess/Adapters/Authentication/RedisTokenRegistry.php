<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use DateTimeImmutable;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use Throwable;

use function is_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class RedisTokenRegistry implements TokenRegistry
{
    private const string ACTIVATE = <<<'LUA'
local ttl = tonumber(ARGV[3]) - tonumber(redis.call('TIME')[1])
if ttl <= 0 then return 0 end
if redis.call('EXISTS', KEYS[3]) == 1 or redis.call('EXISTS', KEYS[1]) == 1 or redis.call('EXISTS', KEYS[2]) == 1 then return 0 end
local function write(command, ...)
    local result = redis.pcall(command, ...)
    if type(result) == 'table' and result.err then
        redis.call('DEL', KEYS[1], KEYS[2])
        error(result.err)
    end
end
for index = 1, 2 do
    write('JSON.SET', KEYS[index], '$', ARGV[index], 'NX')
    write('EXPIRE', KEYS[index], ttl)
    write('EXPIREAT', KEYS[index], ARGV[3])
end
return 1
LUA;

    private const string ASSERT_ACTIVE = <<<'LUA'
local function record(key)
    if redis.call('TYPE', key).ok == 'string' then return redis.call('GET', key) end
    return redis.call('JSON.GET', key, '.')
end
return {record(KEYS[1]), record(KEYS[2]), redis.call('EXISTS', KEYS[3])}
LUA;

    public function __construct(private RedisJsonInterface $redisJson)
    {
    }

    public function activate(Principal $principal, DateTimeImmutable $expiresAt): void
    {
        [$token, $session, $blocked] = self::keys($principal);
        [$tokenValue, $sessionValue] = self::records($principal);
        $result                      = $this->evaluate(self::ACTIVATE, [$token, $session, $blocked, $tokenValue, $sessionValue, $expiresAt->getTimestamp()], 3);
        if ($result !== 1) {
            throw AuthenticationDenied::create('The session could not be activated.');
        }
    }

    public function assertActive(Principal $principal): void
    {
        $values                      = $this->evaluate(self::ASSERT_ACTIVE, self::keys($principal), 3);
        [$tokenValue, $sessionValue] = self::records($principal);
        if (! is_array($values) || $values !== [$tokenValue, $sessionValue, 0]) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function revokeSession(Principal $principal): void
    {
        [$token, $session] = self::keys($principal);
        $this->evaluate("return redis.call('DEL', KEYS[1], KEYS[2])", [$token, $session], 2);
    }

    public function revokeToken(Principal $principal): void
    {
        [$token] = self::keys($principal);
        $this->evaluate("return redis.call('DEL', KEYS[1])", [$token], 1);
    }

    public function revokeAccount(string $accountId): void
    {
        foreach (['USER', 'ADMIN'] as $realm) {
            $key = 'identity:{' . $realm . ':' . $accountId . '}:blocked';
            $this->evaluate("return redis.call('SET', KEYS[1], '1', 'EX', 3600)", [$key], 1);
        }
    }

    /** @return array{string, string, string} */
    private static function keys(Principal $principal): array
    {
        $identity = $principal->identity();
        $realm    = $principal->realm();
        $prefix   = 'identity:{' . $realm->value . ':' . $identity['accountId'] . '}:';

        return [$prefix . 'jti:' . $identity['jti'], $prefix . 'sid:' . $identity['sid'], $prefix . 'blocked'];
    }

    /** @return array{string, string} */
    private static function records(Principal $principal): array
    {
        $claims = $principal->claims();
        $token  = json_encode($claims, JSON_THROW_ON_ERROR);
        unset($claims['jti']);

        return [$token, json_encode($claims, JSON_THROW_ON_ERROR)];
    }

    /** @param list<string|int> $arguments */
    private function evaluate(string $script, array $arguments, int $keyCount): mixed
    {
        try {
            $result = $this->redisJson->raw('EVAL', $script, $keyCount, ...$arguments);
            if ($result === false) {
                throw AuthenticationUnavailable::create('Authentication state is temporarily unavailable.');
            }

            return $result;
        } catch (Throwable $exception) {
            throw AuthenticationUnavailable::create('Authentication state is temporarily unavailable.', previous: $exception);
        }
    }
}
