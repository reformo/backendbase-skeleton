<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\AccountSnapshot;
use Backendbase\Domain\IdentityAndAccess\Domain\Profiles\AccountNames;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;

final class Account
{
    private function __construct(private readonly IdentityId $id, private AccountSnapshot $state)
    {
    }

    public static function create(IdentityId $id, AccountEmail $email, AccountMobile $mobile, DateTimeImmutable $now, AccountNames|null $names = null): self
    {
        $emailValue  = $email->toString();
        $mobileValue = $mobile->toString();
        $createdAt   = $now->format('Y-m-d H:i:s.u');
        $profile     = $names ?? new AccountNames();
        $nameValues  = $profile->snapshot();

        return self::reconstitute($id, [...$nameValues, 'email' => $emailValue, 'mobile' => $mobileValue, 'ownershipVerified' => false, 'registrationAccepted' => false, 'enabled' => true, 'authVersion' => 1, 'deletedAt' => null, 'revision' => 1, 'createdAt' => $createdAt]);
    }

    /** @param array<array-key, mixed> $state */
    public static function reconstitute(IdentityId $id, array $state): self
    {
        return new self($id, AccountSnapshot::fromArray($state));
    }

    public function acceptRegistration(): void
    {
        $this->assertActive();
        $state          = $this->state;
        $authentication = $state->authentication;
        $changed        = $authentication->with(registrationAccepted: true);
        $state          = $state->withAuthentication($changed, null);
        $this->state    = $state;
    }

    public function verifyOwnership(): void
    {
        $this->assertActive();
        $state          = $this->state;
        $authentication = $state->authentication;
        $changed        = $authentication->with(ownershipVerified: true);
        $state          = $state->withAuthentication($changed, null);
        $this->state    = $state;
    }

    public function assertActive(): void
    {
        $state          = $this->state;
        $authentication = $state->authentication;
        $enabled        = $authentication->enabled;
        if (! $enabled || $state->deletedAt !== null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function assertEligible(Realm $realm): void
    {
        $this->assertActive();
        $state          = $this->state;
        $authentication = $state->authentication;
        $accepted       = $authentication->registrationAccepted;
        if ($realm === Realm::ADMIN) {
            $accepted = $authentication->ownershipVerified;
        }

        if (! $accepted) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function assertSessionAllowed(int $expectedVersion, Realm $realm): void
    {
        $this->assertEligible($realm);
        $state          = $this->state;
        $authentication = $state->authentication;
        if ($authentication->authVersion !== $expectedVersion) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function startSession(Realm $realm, Actor $actor): Principal
    {
        $this->assertEligible($realm);
        $accountId = $this->id();
        $actor->assertSessionIdentity($realm, $accountId);
        $actorId        = $actor->id();
        $state          = $this->state;
        $authentication = $state->authentication;

        return Principal::start($realm, $accountId, $actorId, $authentication->authVersion);
    }

    public function contactFor(Realm $realm, string|null $type): ContactValue
    {
        if ($realm === Realm::USER && $type !== 'email') {
            throw InvalidUserInput::create('Select email for User contact verification after login.');
        }

        if ($type !== 'email' && $type !== 'mobile') {
            throw InvalidUserInput::create('Select email or mobile.');
        }

        $state = $this->state;
        $value = $state->email;
        if ($type === 'mobile') {
            $value = $state->mobile;
        }

        return new ContactValue($type, $value);
    }

    public function close(DateTimeImmutable $now): void
    {
        $this->assertActive();
        $state          = $this->state;
        $authentication = $state->authentication;
        $version        = $authentication->authVersion;
        $changed        = $authentication->with(enabled: false, authVersion: $version + 1);
        $deletedAt      = $now->format('Y-m-d H:i:s.u');
        $state          = $state->withAuthentication($changed, $deletedAt);
        $this->state    = $state;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function snapshot(): AccountSnapshot
    {
        return $this->state;
    }
}
