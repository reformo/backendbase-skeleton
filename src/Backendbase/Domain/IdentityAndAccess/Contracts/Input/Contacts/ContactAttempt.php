<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Exception\InvalidUserInput;

use function in_array;

final readonly class ContactAttempt
{
    public function __construct(
        public string $action,
        public Realm $realm,
        public string|null $contactId,
        public VerificationContextToken $token,
        public VerificationClient $client,
        public ContactValue|null $contact,
        public string|null $contactType,
        public VerificationCode|null $code,
        public Principal|null $principal,
        public LoginInput|null $login,
    ) {
        if (! in_array($action, ['request', 'confirm', 'resend'], true)) {
            throw InvalidUserInput::create('The contact verification action is invalid.');
        }

        if ($contactId === null) {
            return;
        }

        IdentityId::fromString($contactId);
    }
}
