<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Challenges;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;

final class Challenge
{
    /** @param array{accountId: string, actorId: string, realm: string, purpose: string, proofHash: string, expiresAt: string, attempts: int, consumed: bool, authVersion: int, revision: int} $state */
    public function __construct(private readonly IdentityId $id, private array $state)
    {
        IdentityId::fromString($state['accountId']);
        IdentityId::fromString($state['actorId']);
        $this->state['realm'] = Realm::from($state['realm'])->value;
        $purpose              = ChallengePurpose::tryFrom($state['purpose']);
        if ($purpose === null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $this->state['purpose'] = $purpose->value;
    }

    public function permitsAttempt(ChallengePurpose $purpose, Realm $realm, DateTimeImmutable $now): bool
    {
        return $this->state['purpose'] === $purpose->value && $this->state['realm'] === $realm->value && ! $this->state['consumed'] && $this->state['attempts'] < 5 && $this->state['expiresAt'] > $now->format('Y-m-d H:i:s.u');
    }

    public function verify(ChallengePurpose $purpose, Realm $realm, bool $validProof, DateTimeImmutable $now): bool
    {
        if (! $this->permitsAttempt($purpose, $realm, $now)) {
            return false;
        }

        $this->state['attempts']++;
        if (! $validProof) {
            return false;
        }

        $this->state['consumed'] = true;

        return true;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    /** @return array{accountId: string, actorId: string, realm: string, purpose: string, proofHash: string, expiresAt: string, attempts: int, consumed: bool, authVersion: int, revision: int} */
    public function snapshot(): array
    {
        return $this->state;
    }
}
