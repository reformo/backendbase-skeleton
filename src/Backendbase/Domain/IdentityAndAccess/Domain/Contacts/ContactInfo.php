<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use DateTimeImmutable;
use DateTimeZone;

final class ContactInfo
{
    /** @param array{realm: string, purpose: string, contactType: string, contactValue: string, contextHash: string, accountId: string|null, actorId: string|null, generationId: string, codeHash: string, codeExpiresAt: string, failedAttempts: int, requestedAt: string, lastSentAt: string, verifiedAt: string|null, consumedAt: string|null, deliveryState: string, revision: int} $state */
    public function __construct(private readonly IdentityId $id, private array $state)
    {
        new ContactValue($state['contactType'], $state['contactValue']);
    }

    public static function request(IdentityId $id, ContactValue $contact, VerificationContextToken $token, VerificationScope $scope, DateTimeImmutable $now): self
    {
        $owner     = $scope->owner();
        $type      = $contact->type();
        $value     = $contact->value();
        $digest    = $token->digest();
        $realm     = $scope->realm();
        $purpose   = $scope->purpose();
        $timestamp = $now->format('Y-m-d H:i:s.u');

        return new self($id, ['realm' => $realm, 'purpose' => $purpose, 'contactType' => $type, 'contactValue' => $value, 'contextHash' => $digest, ...$owner, 'generationId' => '', 'codeHash' => '', 'codeExpiresAt' => $timestamp, 'failedAttempts' => 0, 'requestedAt' => $timestamp, 'lastSentAt' => $timestamp, 'verifiedAt' => null, 'consumedAt' => null, 'deliveryState' => 'pending', 'revision' => 1]);
    }

    public function assertContext(VerificationContextToken $token, VerificationScope $scope): void
    {
        $owner = $scope->owner();
        if (! $token->matches($this->state['contextHash']) || $this->state['realm'] !== $scope->realm() || $this->state['purpose'] !== $scope->purpose() || $owner !== ['accountId' => $this->state['accountId'], 'actorId' => $this->state['actorId']]) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }
    }

    public function assertFresh(DateTimeImmutable $now): void
    {
        if ($this->state['consumedAt'] !== null) {
            throw IdentityConflict::create('The verification evidence has been consumed.');
        }

        $verified = $this->state['verifiedAt'];
        if ($verified === null) {
            throw AuthenticationDenied::create('Both contacts require verification.');
        }

        $date = new DateTimeImmutable($verified, new DateTimeZone('UTC'));
        if ($date > $now || $date->modify('+12 hours') <= $now) {
            throw ContactVerificationExpired::create('The contact verification has expired.');
        }
    }

    public function assertConfirmationAllowed(DateTimeImmutable $now): void
    {
        if ($this->state['failedAttempts'] >= 5) {
            throw VerificationLimited::create('The verification attempt limit was reached.', ['retryAfter' => 3600]);
        }

        if ($this->state['verifiedAt'] !== null) {
            $this->assertFresh($now);

            return;
        }

        if ($this->state['codeExpiresAt'] <= $now->format('Y-m-d H:i:s.u')) {
            throw ContactVerificationExpired::create('The verification code has expired.');
        }
    }

    public function confirm(bool $valid, DateTimeImmutable $now): bool
    {
        $this->assertConfirmationAllowed($now);
        if (! $valid) {
            $this->state['failedAttempts']++;

            return false;
        }

        $this->state['verifiedAt'] ??= $now->format('Y-m-d H:i:s.u');

        return true;
    }

    public function assertResendAllowed(DateTimeImmutable $now): void
    {
        if ($this->state['verifiedAt'] !== null || $this->state['consumedAt'] !== null) {
            throw IdentityConflict::create('Verified contact evidence cannot be renewed.');
        }

        if ($this->state['failedAttempts'] >= 5) {
            throw VerificationLimited::create('The verification attempt limit was reached.', ['retryAfter' => 3600]);
        }

        $previous = new DateTimeImmutable($this->state['lastSentAt'], new DateTimeZone('UTC'));
        if ($previous->modify('+60 seconds') > $now) {
            throw VerificationLimited::create('Wait before requesting another code.', ['retryAfter' => 60]);
        }
    }

    public function issue(string $generationId, string $codeHash, DateTimeImmutable $now): void
    {
        $expires                      = $now->modify('+5 minutes');
        $this->state['generationId']  = $generationId;
        $this->state['codeHash']      = $codeHash;
        $this->state['codeExpiresAt'] = $expires->format('Y-m-d H:i:s.u');
        $this->state['lastSentAt']    = $now->format('Y-m-d H:i:s.u');
        $this->state['deliveryState'] = 'pending';
    }

    public function consume(string $accountId, string $actorId, DateTimeImmutable $now): void
    {
        $this->assertFresh($now);
        $this->state['accountId']  = $accountId;
        $this->state['actorId']    = $actorId;
        $this->state['consumedAt'] = $now->format('Y-m-d H:i:s.u');
    }

    public function recordDelivery(string $generationId, string $status): void
    {
        if ($this->state['generationId'] !== $generationId) {
            return;
        }

        $this->state['deliveryState'] = $status;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function contact(): ContactValue
    {
        return new ContactValue($this->state['contactType'], $this->state['contactValue']);
    }

    /** @return array{realm: string, purpose: string, contactType: string, contactValue: string, contextHash: string, accountId: string|null, actorId: string|null, generationId: string, codeHash: string, codeExpiresAt: string, failedAttempts: int, requestedAt: string, lastSentAt: string, verifiedAt: string|null, consumedAt: string|null, deliveryState: string, revision: int} */
    public function snapshot(): array
    {
        return $this->state;
    }
}
