<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;

interface ContactNotifier
{
    public function send(string $generationId, ContactValue $contact, string $code): void;
}
