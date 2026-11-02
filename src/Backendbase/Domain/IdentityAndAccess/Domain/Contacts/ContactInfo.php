<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ContactState;
use DateTimeImmutable;
use DateTimeZone;

final class ContactInfo
{
    public function __construct(private readonly IdentityId $id, private ContactState $state)
    {
    }

    public static function request(IdentityId $id, ContactValue $contact, VerificationContextToken $token, VerificationScope $scope, DateTimeImmutable $now): self
    {
        return new self($id, ContactState::forRequest($contact, $token, $scope, $now));
    }

    public function assertContext(VerificationContextToken $token, VerificationScope $scope): void
    {
        $state        = $this->state;
        $contextHash  = $state->contextHash;
        $tokenMatches = $token->matches($contextHash);
        if (! $tokenMatches || ! $scope->matches($state)) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }
    }

    public function assertFresh(DateTimeImmutable $now): void
    {
        $state = $this->state;
        if ($state->consumedAt !== null) {
            throw IdentityConflict::create('The verification evidence has been consumed.');
        }

        $verifiedAt = $state->verifiedAt;
        if ($verifiedAt === null) {
            throw AuthenticationDenied::create('The contact requires verification.');
        }

        $verificationTime = new DateTimeImmutable($verifiedAt, new DateTimeZone('UTC'));
        if ($verificationTime > $now || $verificationTime->modify(ContactVerificationPolicy::EVIDENCE_LIFETIME) <= $now) {
            throw ContactVerificationExpired::create('The contact verification has expired.');
        }
    }

    public function assertConfirmationAllowed(DateTimeImmutable $now): void
    {
        $state = $this->state;
        if ($state->failedAttempts >= ContactVerificationPolicy::MAXIMUM_FAILED_ATTEMPTS) {
            throw VerificationLimited::create('The verification attempt limit was reached.', ['retryAfter' => ContactVerificationPolicy::FAILED_ATTEMPT_RETRY_SECONDS]);
        }

        if ($state->verifiedAt !== null) {
            $this->assertFresh($now);

            return;
        }

        $timestamp = $now->format('Y-m-d H:i:s.u');
        if ($state->codeExpiresAt <= $timestamp) {
            throw ContactVerificationExpired::create('The verification code has expired.');
        }
    }

    public function confirm(bool $valid, DateTimeImmutable $now): bool
    {
        $this->assertConfirmationAllowed($now);
        $state       = $this->state;
        $attempts    = $state->failedAttempts;
        $verifiedAt  = $state->verifiedAt;
        $timestamp   = $now->format('Y-m-d H:i:s.u');
        $state       = $state->with(failedAttempts: $valid ? $attempts : $attempts + 1, verifiedAt: $valid ? ($verifiedAt ?? $timestamp) : $verifiedAt);
        $this->state = $state;

        return $valid;
    }

    public function assertResendAllowed(DateTimeImmutable $now): void
    {
        $state      = $this->state;
        $verifiedAt = $state->verifiedAt;
        if ($verifiedAt !== null || $state->consumedAt !== null) {
            throw IdentityConflict::create('Verified contact evidence cannot be renewed.');
        }

        if ($state->failedAttempts >= ContactVerificationPolicy::MAXIMUM_FAILED_ATTEMPTS) {
            throw VerificationLimited::create('The verification attempt limit was reached.', ['retryAfter' => ContactVerificationPolicy::FAILED_ATTEMPT_RETRY_SECONDS]);
        }

        $lastSentAt = new DateTimeImmutable($state->lastSentAt, new DateTimeZone('UTC'));
        if ($lastSentAt->modify(ContactVerificationPolicy::RESEND_COOLDOWN) > $now) {
            throw VerificationLimited::create('Wait before requesting another code.', ['retryAfter' => ContactVerificationPolicy::RESEND_RETRY_SECONDS]);
        }
    }

    public function issue(string $generationId, string $codeHash, DateTimeImmutable $now): void
    {
        $expires     = $now->modify(ContactVerificationPolicy::CODE_LIFETIME);
        $expiresAt   = $expires->format('Y-m-d H:i:s.u');
        $lastSentAt  = $now->format('Y-m-d H:i:s.u');
        $state       = $this->state;
        $state       = $state->with(generationId: $generationId, codeHash: $codeHash, codeExpiresAt: $expiresAt, lastSentAt: $lastSentAt, deliveryState: 'pending');
        $this->state = $state;
    }

    public function consume(string $accountId, string $actorId, DateTimeImmutable $now): void
    {
        $this->assertFresh($now);
        $state       = $this->state;
        $consumedAt  = $now->format('Y-m-d H:i:s.u');
        $state       = $state->with(accountId: $accountId, actorId: $actorId, consumedAt: $consumedAt);
        $this->state = $state;
    }

    public function recordDelivery(string $generationId, string $status): void
    {
        $state                    = $this->state;
        $matchesCurrentGeneration = $state->generationId === $generationId;
        if (! $matchesCurrentGeneration) {
            return;
        }

        $state       = $state->with(deliveryState: $status);
        $this->state = $state;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function contact(): ContactValue
    {
        return ContactValue::fromState($this->state);
    }

    public function snapshot(): ContactState
    {
        return $this->state;
    }
}
