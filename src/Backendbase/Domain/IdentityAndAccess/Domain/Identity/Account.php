<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use DateTimeImmutable;

final class Account
{
    /** @param array{email: string, owned: bool, enabled: bool, authVersion: int, deletedAt: string|null, revision: int, createdAt: string} $state */
    private function __construct(private readonly IdentityId $id, private array $state)
    {
        new AccountEmail($state['email']);
    }

    public static function create(IdentityId $id, AccountEmail $email, DateTimeImmutable $now): self
    {
        return new self($id, ['email' => $email->toString(), 'owned' => false, 'enabled' => true, 'authVersion' => 1, 'deletedAt' => null, 'revision' => 1, 'createdAt' => $now->format('Y-m-d H:i:s.u')]);
    }

    /** @param array{email: string, owned: bool, enabled: bool, authVersion: int, deletedAt: string|null, revision: int, createdAt: string} $state */
    public static function reconstitute(IdentityId $id, array $state): self
    {
        return new self($id, $state);
    }

    public function verifyOwnership(): void
    {
        $this->assertActive();
        $this->state['owned'] = true;
    }

    public function assertActive(): void
    {
        if (! $this->state['enabled'] || $this->state['deletedAt'] !== null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function assertSessionAllowed(int $expectedVersion): void
    {
        $this->assertActive();
        if (! $this->state['owned'] || $this->state['authVersion'] !== $expectedVersion) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function close(DateTimeImmutable $now): void
    {
        $this->assertActive();
        $this->state['enabled']   = false;
        $this->state['deletedAt'] = $now->format('Y-m-d H:i:s.u');
        $this->state['authVersion']++;
    }

    public function revokeCredentials(): void
    {
        $this->assertActive();
        $this->state['authVersion']++;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    /** @return array{email: string, owned: bool, enabled: bool, authVersion: int, deletedAt: string|null, revision: int, createdAt: string} */
    public function snapshot(): array
    {
        return $this->state;
    }
}
