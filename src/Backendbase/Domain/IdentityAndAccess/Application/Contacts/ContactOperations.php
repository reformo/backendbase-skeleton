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
        $action   = $input->action;
        $token    = $input->token;
        $client   = $input->client;
        $requests = $this->requests;
        if ($action === 'request') {
            $contact ??= throw InvalidUserInput::create('A contact is required.');

            return $requests->request($contact, $token, $scope, $client);
        }

        $id = $input->contactId ?? throw InvalidUserInput::create('A contact identifier is required.');
        if ($action === 'resend') {
            return $requests->resend($id, $token, $scope, $client);
        }

        $code         = $input->code ?? throw InvalidUserInput::create('A verification code is required.');
        $confirmation = $this->confirmation;

        return [$confirmation->confirm($id, $token, $scope, $code), null];
    }
}
