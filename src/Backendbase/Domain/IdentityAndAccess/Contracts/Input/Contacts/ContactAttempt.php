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
    /** @param array{action: string, realm: Realm, contactId: string|null, token: VerificationContextToken, client: VerificationClient, contact: ContactValue|null, contactType: string|null, code: VerificationCode|null, principal: Principal|null, login: LoginInput|null} $values */
    public function __construct(private array $values)
    {
        if (! in_array($values['action'], ['request', 'confirm', 'resend'], true)) {
            throw InvalidUserInput::create('The contact verification action is invalid.');
        }

        if ($values['contactId'] === null) {
            return;
        }

        IdentityId::fromString($values['contactId']);
    }

    /** @return array{action: string, realm: Realm, contactId: string|null, token: VerificationContextToken, client: VerificationClient, contact: ContactValue|null, contactType: string|null, code: VerificationCode|null, principal: Principal|null, login: LoginInput|null} */
    public function values(): array
    {
        return $this->values;
    }
}
