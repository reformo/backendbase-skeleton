<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Profiles\AccountNames;
use DateTimeImmutable;

final class Account
{
    /** @param array{firstName?: string|null, lastName?: string|null, email: string, mobile: string, ownershipVerified: bool, registrationAccepted: bool, enabled: bool, authVersion: int, deletedAt: string|null, revision: int, createdAt: string} $state */
    private function __construct(private readonly IdentityId $id, private array $state)
    {
        new AccountEmail($state['email']);
        new AccountMobile($state['mobile']);
        new AccountNames($state['firstName'] ?? null, $state['lastName'] ?? null);
    }

    public static function create(IdentityId $id, AccountEmail $email, AccountMobile $mobile, DateTimeImmutable $now, AccountNames|null $names = null): self
    {
        $emailValue  = $email->toString();
        $mobileValue = $mobile->toString();
        $createdAt   = $now->format('Y-m-d H:i:s.u');

        $profile    = $names ?? new AccountNames();
        $nameValues = $profile->snapshot();

        return new self($id, [...$nameValues, 'email' => $emailValue, 'mobile' => $mobileValue, 'ownershipVerified' => false, 'registrationAccepted' => false, 'enabled' => true, 'authVersion' => 1, 'deletedAt' => null, 'revision' => 1, 'createdAt' => $createdAt]);
    }

    /** @param array{firstName?: string|null, lastName?: string|null, email: string, mobile: string, ownershipVerified: bool, registrationAccepted?: bool, enabled: bool, authVersion: int, deletedAt: string|null, revision: int, createdAt: string} $state */
    public static function reconstitute(IdentityId $id, array $state): self
    {
        return new self($id, ['registrationAccepted' => $state['ownershipVerified'], ...$state]);
    }

    public function acceptRegistration(): void
    {
        $this->assertActive();
        $this->state['registrationAccepted'] = true;
    }

    public function verifyOwnership(): void
    {
        $this->assertActive();
        $this->state['ownershipVerified'] = true;
    }

    public function assertActive(): void
    {
        if (! $this->state['enabled'] || $this->state['deletedAt'] !== null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function assertSessionAllowed(int $expectedVersion, Realm $realm): void
    {
        $this->assertActive();
        $state    = $this->state;
        $accepted = $realm === Realm::USER ? $state['registrationAccepted'] : $state['ownershipVerified'];
        if (! $accepted || $state['authVersion'] !== $expectedVersion) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function startSession(Realm $realm, Actor $actor): Principal
    {
        $this->assertSessionAllowed($this->state['authVersion'], $realm);
        $accountId = $this->id();
        $actor->assertSessionIdentity($realm, $accountId);
        $actorId           = $actor->id();
        $sessionId         = IdentityId::create();
        $tokenId           = IdentityId::create();
        $sessionIdentifier = $sessionId->toString();
        $tokenIdentifier   = $tokenId->toString();

        return new Principal($realm, [
            'accountId' => $accountId,
            'actorId' => $actorId,
            'sid' => $sessionIdentifier,
            'jti' => $tokenIdentifier,
            'authVersion' => $this->state['authVersion'],
        ]);
    }

    public function close(DateTimeImmutable $now): void
    {
        $this->assertActive();
        $this->state['enabled']   = false;
        $this->state['deletedAt'] = $now->format('Y-m-d H:i:s.u');
        $this->state['authVersion']++;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    /** @return array{firstName?: string|null, lastName?: string|null, email: string, mobile: string, ownershipVerified: bool, registrationAccepted: bool, enabled: bool, authVersion: int, deletedAt: string|null, revision: int, createdAt: string} */
    public function snapshot(): array
    {
        return $this->state;
    }
}
