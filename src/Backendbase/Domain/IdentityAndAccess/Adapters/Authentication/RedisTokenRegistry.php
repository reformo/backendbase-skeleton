<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions\RedisRenewableSessions;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions\RedisSessionConnection;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions\SessionRecords;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions\SessionValidation;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use DateTimeImmutable;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function is_array;

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

    private RedisSessionConnection $connection;
    private RedisRenewableSessions $sessions;

    public function __construct(RedisJsonInterface $redisJson)
    {
        $connection       = new RedisSessionConnection($redisJson);
        $this->connection = $connection;
        $this->sessions   = new RedisRenewableSessions($connection);
    }

    public function activate(Principal $principal, DateTimeImmutable $expiresAt, SessionState|null $session = null): void
    {
        if ($session !== null) {
            $sessions = $this->sessions;
            $sessions->activate($session, $expiresAt);

            return;
        }

        [$token, $sessionKey, $blocked] = SessionRecords::keys($principal);
        [$tokenValue, $sessionValue]    = SessionRecords::legacy($principal);
        $connection                     = $this->connection;
        $timestamp                      = $expiresAt->getTimestamp();
        $result                         = $connection->evaluate(self::ACTIVATE, [$token, $sessionKey, $blocked, $tokenValue, $sessionValue, $timestamp], 3);
        if ($result !== 1) {
            throw AuthenticationDenied::create('The session could not be activated.');
        }
    }

    public function assertActive(Principal $principal, bool $touch = false): void
    {
        [$tokenValue, $sessionValue] = SessionRecords::legacy($principal);
        $connection                  = $this->connection;
        $values                      = $connection->evaluate(SessionValidation::SCRIPT, [...SessionRecords::keys($principal), $tokenValue, $sessionValue, (int) $touch], 3);
        if (! is_array($values) || $values !== [$tokenValue, $sessionValue, 0]) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function revokeSession(Principal $principal): void
    {
        [$token, $session] = SessionRecords::keys($principal);
        $connection        = $this->connection;
        $connection->evaluate("return redis.call('DEL', KEYS[1], KEYS[2])", [$token, $session], 2);
    }

    public function revokeToken(Principal $principal): void
    {
        [$token]    = SessionRecords::keys($principal);
        $connection = $this->connection;
        $connection->evaluate("return redis.call('DEL', KEYS[1])", [$token], 1);
    }

    public function revokeAccount(string $accountId): void
    {
        foreach (['USER', 'ADMIN'] as $realm) {
            $key        = 'identity:{' . $realm . ':' . $accountId . '}:blocked';
            $connection = $this->connection;
            $connection->evaluate("return redis.call('SET', KEYS[1], '1', 'EX', 3600)", [$key], 1);
        }
    }

    public function session(Realm $realm, RefreshToken $token, bool $allowUsed = false): SessionState
    {
        $sessions = $this->sessions;

        return $sessions->session($realm, $token, $allowUsed);
    }

    public function rotate(RefreshToken $provided, SessionState $replacement, DateTimeImmutable $expiresAt): void
    {
        $sessions = $this->sessions;
        $sessions->rotate($provided, $replacement, $expiresAt);
    }
}
