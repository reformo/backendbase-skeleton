<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use DateTimeImmutable;
use Throwable;

use function is_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class RedisTokenRegistry implements TokenRegistry
{
    private const string ACTIVATE = <<<'LUA'
if redis.call('EXISTS', KEYS[3]) == 1 or redis.call('EXISTS', KEYS[1]) == 1 or redis.call('EXISTS', KEYS[2]) == 1 then return 0 end
redis.call('SET', KEYS[1], ARGV[1], 'EXAT', ARGV[3])
redis.call('SET', KEYS[2], ARGV[2], 'EXAT', ARGV[3])
return 1
LUA;

    public function __construct(private SessionRedis $connection)
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
        $values                      = $this->evaluate("return {redis.call('GET', KEYS[1]), redis.call('GET', KEYS[2]), redis.call('EXISTS', KEYS[3])}", self::keys($principal), 3);
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
            $connection = $this->connection;
            $client     = $connection->client();

            $result = $client->eval($script, $arguments, $keyCount);
            if ($result === false) {
                throw AuthenticationUnavailable::create('Authentication state is temporarily unavailable.');
            }

            return $result;
        } catch (Throwable $exception) {
            throw AuthenticationUnavailable::create('Authentication state is temporarily unavailable.', previous: $exception);
        }
    }
}
