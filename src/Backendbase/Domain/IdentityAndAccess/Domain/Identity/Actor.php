<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use DateTimeImmutable;

abstract class Actor
{
    /** @param array{accountId: string, loginName: string, approved: bool, invitation: string|null, approval: string|null, deletedAt: string|null, revision: int, createdAt: string} $state */
    final public function __construct(private readonly IdentityId $id, private array $state)
    {
        IdentityId::fromString($state['accountId']);
        new LoginName($state['loginName']);
        if ($this->realm() === Realm::ADMIN && ($state['invitation'] === null || ($state['approved'] && $state['approval'] === null))) {
            throw AuthenticationDenied::create('The Admin authorization state is invalid.');
        }
    }

    abstract public function realm(): Realm;

    public function assertSessionAllowed(): void
    {
        if (! $this->state['approved'] || $this->state['deletedAt'] !== null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    protected function approveWith(string $evidence): void
    {
        $this->state['approved'] = true;
        $this->state['approval'] = $evidence;
    }

    public function remove(DateTimeImmutable $now): void
    {
        $this->state['deletedAt'] = $now->format('Y-m-d H:i:s.u');
    }

    public function restore(): void
    {
        $this->state['deletedAt'] = null;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    /** @return array{accountId: string, loginName: string, approved: bool, invitation: string|null, approval: string|null, deletedAt: string|null, revision: int, createdAt: string} */
    public function snapshot(): array
    {
        return $this->state;
    }
}
