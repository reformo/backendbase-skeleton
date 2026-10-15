<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;

interface VerificationLimiter
{
    public function send(ContactValue $contact, VerificationClient $client): void;

    public function attempt(ContactValue $contact): void;

    public function accept(ContactValue $contact): void;
}
