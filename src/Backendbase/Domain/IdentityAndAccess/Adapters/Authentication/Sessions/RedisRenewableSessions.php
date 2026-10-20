<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use DateTimeImmutable;

use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class RedisRenewableSessions
{
    public function __construct(private RedisSessionConnection $connection)
    {
    }

    public function activate(SessionState $state, DateTimeImmutable $expiresAt): void
    {
        $principal  = $state->principal;
        [$token]    = SessionRecords::legacy($principal);
        $session    = json_encode($state->toArray(), JSON_THROW_ON_ERROR);
        $connection = $this->connection;
        $timestamp  = $expiresAt->getTimestamp();
        $result     = $connection->evaluate(SessionActivation::SCRIPT, [...SessionRecords::keys($principal), $token, $session, $timestamp], 3);
        self::accepted($result);
    }

    public function session(Realm $realm, RefreshToken $token, bool $allowUsed): SessionState
    {
        $connection = $this->connection;
        $digest     = $token->digest();
        $value      = $connection->evaluate(SessionRenewal::LOOKUP, [...SessionRecords::refreshKeys($realm, $token), $digest, (int) $allowUsed], 3);
        if (! is_string($value)) {
            throw AuthenticationDenied::create('The session cannot be renewed.');
        }

        $state       = RedisSessionConnection::decode($value);
        $principal   = $state->principal;
        $identity    = $principal->identity();
        $actualRealm = $principal->realm();
        $accountId   = $token->accountId();
        $sessionId   = $token->sessionId();
        if ($actualRealm !== $realm || $identity['accountId'] !== $accountId || $identity['sid'] !== $sessionId) {
            throw AuthenticationDenied::create('The session cannot be renewed.');
        }

        return $state;
    }

    public function rotate(RefreshToken $provided, SessionState $replacement, DateTimeImmutable $expiresAt): void
    {
        $principal                         = $replacement->principal;
        $realm                             = $principal->realm();
        [$tokenKey, $sessionKey, $blocked] = SessionRecords::keys($principal);
        [, $used]                          = SessionRecords::refreshKeys($realm, $provided);
        [$token]                           = SessionRecords::legacy($principal);
        $session                           = json_encode($replacement->toArray(), JSON_THROW_ON_ERROR);
        $connection                        = $this->connection;
        $digest                            = $provided->digest();
        $timestamp                         = $expiresAt->getTimestamp();
        $result                            = $connection->evaluate(SessionRenewal::ROTATE, [$tokenKey, $sessionKey, $blocked, $used, $digest, $token, $session, $timestamp], 4);
        self::accepted($result);
    }

    private static function accepted(mixed $result): void
    {
        if ($result !== 1) {
            throw AuthenticationDenied::create('The session could not be activated or renewed.');
        }
    }
}
