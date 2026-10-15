<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use DateTimeImmutable;

final readonly class ContactIssuance
{
    public function __construct(private VerificationTools $tools, private VerificationLimiter $limiter)
    {
    }

    public function reserve(ContactValue $contact, VerificationClient $client): void
    {
        $limiter = $this->limiter;
        $limiter->send($contact, $client);
    }

    public function issue(ContactInfo $contact, VerificationCode $code, DateTimeImmutable $now): void
    {
        $tools = $this->tools;
        $tools->issue($contact, $code, $now);
    }

    public function replacement(ContactInfo $contact): VerificationCode
    {
        $tools = $this->tools;
        do {
            $code = VerificationCode::generate();
        } while ($tools->valid($contact, $code));

        return $code;
    }

    public function now(): DateTimeImmutable
    {
        $tools = $this->tools;

        return $tools->now();
    }
}
