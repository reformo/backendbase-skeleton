<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Shared\Exception\InvalidUserInput;

final readonly class ContactOperations
{
    public function __construct(private ContactRequests $requests, private ContactConfirmation $confirmation)
    {
    }

    /** @return array{ContactInfo, VerificationCode|null} */
    public function execute(ContactAttempt $input, VerificationScope $scope, ContactValue|null $contact): array
    {
        $values   = $input->values();
        $requests = $this->requests;
        if ($values['action'] === 'request') {
            $contact ??= throw InvalidUserInput::create('A contact is required.');

            return $requests->request($contact, $values['token'], $scope, $values['client']);
        }

        $id = $values['contactId'] ?? throw InvalidUserInput::create('A contact identifier is required.');
        if ($values['action'] === 'resend') {
            return $requests->resend($id, $values['token'], $scope, $values['client']);
        }

        $code         = $values['code'] ?? throw InvalidUserInput::create('A verification code is required.');
        $confirmation = $this->confirmation;

        return [$confirmation->confirm($id, $values['token'], $scope, $code), null];
    }
}
