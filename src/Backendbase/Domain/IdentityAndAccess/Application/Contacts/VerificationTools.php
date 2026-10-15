<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

final readonly class VerificationTools
{
    public function __construct(private AuthenticationSecrets $secrets, private Clock $clock)
    {
    }

    public function now(): DateTimeImmutable
    {
        $clock = $this->clock;

        return $clock->now();
    }

    public function issue(ContactInfo $contact, VerificationCode $code, DateTimeImmutable $now): void
    {
        $id         = IdentityId::create();
        $generation = $id->toString();
        $contact->issue($generation, '', $now);
        $proof   = $code->proof($contact);
        $secrets = $this->secrets;
        $hash    = $secrets->proofHash($proof);
        $contact->issue($generation, $hash, $now);
    }

    public function valid(ContactInfo $contact, VerificationCode $code): bool
    {
        $proof   = $code->proof($contact);
        $state   = $contact->snapshot();
        $secrets = $this->secrets;

        return $secrets->verifyProof($proof, $state['codeHash']);
    }
}
