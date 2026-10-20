<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ActorSnapshot;
use DateTimeImmutable;

abstract class Actor
{
    private ActorSnapshot $state;

    /** @param array<array-key, mixed> $state */
    final public function __construct(private readonly IdentityId $id, array $state)
    {
        $snapshot      = ActorSnapshot::fromArray($state);
        $authorization = $snapshot->authorization;
        $invitation    = $authorization->invitation;
        $approved      = $authorization->approved;
        $approval      = $authorization->approval;
        if ($this->realm() === Realm::ADMIN && ($invitation === null || ($approved && $approval === null))) {
            throw AuthenticationDenied::create('The Admin authorization state is invalid.');
        }

        $this->state = $snapshot;
    }

    abstract public function realm(): Realm;

    public function assertSessionAllowed(): void
    {
        $state         = $this->state;
        $authorization = $state->authorization;
        $approved      = $authorization->approved;
        if (! $approved || $state->deletedAt !== null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function assertSessionIdentity(Realm $realm, string $accountId): void
    {
        $this->assertSessionAllowed();
        $state        = $this->state;
        $currentRealm = $this->realm();
        if ($currentRealm !== $realm || $state->accountId !== $accountId) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function assertContactOwner(string $accountId): void
    {
        $state = $this->state;
        if ($state->accountId !== $accountId) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }
    }

    public function accountReference(): IdentityId
    {
        $state = $this->state;

        return IdentityId::fromString($state->accountId);
    }

    public function assertContactVerificationAllowed(): void
    {
        $state = $this->state;
        if ($state->deletedAt !== null) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }
    }

    protected function approveWith(string $evidence): void
    {
        $state         = $this->state;
        $authorization = $state->authorization;
        $changed       = $authorization->withApproval(true, $evidence);
        $state         = $state->withAuthorization($changed);
        $this->state   = $state;
    }

    public function remove(DateTimeImmutable $now): void
    {
        $state       = $this->state;
        $deletedAt   = $now->format('Y-m-d H:i:s.u');
        $state       = $state->withDeletedAt($deletedAt);
        $this->state = $state;
    }

    public function restore(): void
    {
        $state       = $this->state;
        $state       = $state->withDeletedAt(null);
        $this->state = $state;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function snapshot(): ActorSnapshot
    {
        return $this->state;
    }
}
