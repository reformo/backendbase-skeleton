<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

final class MemoryTokenRegistry implements TokenRegistry
{
    /** @var array<string, array{Principal, DateTimeImmutable, bool}> */
    private array $records = [];

    public function __construct(private readonly Clock $clock)
    {
    }

    public function activate(Principal $principal, DateTimeImmutable $expiresAt): void
    {
        $identity = $principal->identity();
        $clock    = $this->clock;
        $existing = $this->records[$identity['sid']] ?? null;
        if ($existing !== null && $existing[1] > $clock->now()) {
            throw AuthenticationDenied::create('The session already exists.');
        }

        $this->records[$identity['sid']] = [$principal, $expiresAt, false];
    }

    public function assertActive(Principal $principal): void
    {
        $identity = $principal->identity();
        $record   = $this->records[$identity['sid']] ?? null;
        $clock    = $this->clock;
        if ($record === null || $record[2] || $record[0]->claims() !== $principal->claims() || $record[1] <= $clock->now()) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function revokeSession(Principal $principal): void
    {
        $identity = $principal->identity();
        unset($this->records[$identity['sid']]);
    }

    public function revokeAccount(string $accountId): void
    {
        foreach ($this->records as $sid => [$principal]) {
            $identity = $principal->identity();
            if ($identity['accountId'] !== $accountId) {
                continue;
            }

            unset($this->records[$sid]);
        }
    }

    public function revokeToken(Principal $principal): void
    {
        $identity = $principal->identity();
        if (! isset($this->records[$identity['sid']])) {
            return;
        }

        $this->records[$identity['sid']][2] = true;
    }
}
