<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use DateTimeImmutable;

final readonly class ContactChecks
{
    public function __construct(private VerificationTools $tools, private VerificationLimiter $limiter)
    {
    }

    public function reserve(ContactInfo $contact): void
    {
        $limiter = $this->limiter;
        $limiter->attempt($contact->contact());
    }

    public function accept(ContactInfo $contact): void
    {
        $limiter = $this->limiter;
        $limiter->accept($contact->contact());
    }

    public function now(): DateTimeImmutable
    {
        $tools = $this->tools;

        return $tools->now();
    }

    public function valid(ContactInfo $contact, VerificationCode $code): bool
    {
        $tools = $this->tools;

        return $tools->valid($contact, $code);
    }
}
