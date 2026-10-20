<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\Sessions\MemorySessionRecord;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

final class MemoryTokenRegistry implements TokenRegistry
{
    /** @var array<string, MemorySessionRecord> */
    private array $records = [];

    public function __construct(private readonly Clock $clock)
    {
    }

    public function activate(Principal $principal, DateTimeImmutable $expiresAt, SessionState|null $session = null): void
    {
        $key      = self::key($principal);
        $clock    = $this->clock;
        $now      = $clock->now();
        $existing = $this->records[$key] ?? null;
        if ($existing !== null && $existing->activeAt($now)) {
            throw AuthenticationDenied::create('The session already exists.');
        }

        $this->records[$key] = new MemorySessionRecord($principal, $expiresAt, $session);
    }

    public function assertActive(Principal $principal, bool $touch = false): void
    {
        $record = $this->records[self::key($principal)] ?? null;
        $clock  = $this->clock;
        $now    = $clock->now();
        if ($record === null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $record->assertActive($principal, $now, $touch);
    }

    public function revokeSession(Principal $principal): void
    {
        unset($this->records[self::key($principal)]);
    }

    public function revokeAccount(string $accountId): void
    {
        foreach ($this->records as $sessionId => $record) {
            if (! $record->belongsTo($accountId)) {
                continue;
            }

            unset($this->records[$sessionId]);
        }
    }

    public function revokeToken(Principal $principal): void
    {
        $key = self::key($principal);
        if (! isset($this->records[$key])) {
            return;
        }

        $record = $this->records[$key];
        $record->revokeToken($principal);
    }

    public function session(Realm $realm, RefreshToken $token, bool $allowUsed = false): SessionState
    {
        $record = $this->records[$realm->value . ':' . $token->sessionId()] ?? throw AuthenticationDenied::create('The session cannot be renewed.');
        if (! $record->belongsTo($token->accountId())) {
            throw AuthenticationDenied::create('The session cannot be renewed.');
        }

        $clock = $this->clock;
        $now   = $clock->now();

        return $record->session($token, $now, $allowUsed);
    }

    public function rotate(RefreshToken $provided, SessionState $replacement, DateTimeImmutable $expiresAt): void
    {
        $record = $this->records[self::key($replacement->principal)] ?? throw AuthenticationDenied::create('The session cannot be renewed.');
        $clock  = $this->clock;
        $now    = $clock->now();
        $record->rotate($provided, $replacement, $expiresAt, $now);
    }

    private static function key(Principal $principal): string
    {
        $identity = $principal->identity();
        $realm    = $principal->realm();

        return $realm->value . ':' . $identity['sid'];
    }
}
