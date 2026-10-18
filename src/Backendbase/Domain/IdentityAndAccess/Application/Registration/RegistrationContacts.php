<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

final readonly class RegistrationContacts
{
    public function __construct(private ContactStore $contacts, private Clock $clock)
    {
    }

    /** @return array{ContactInfo|null, ContactInfo|null, DateTimeImmutable} */
    public function acquire(RegistrationInput $input): array
    {
        $token = $input->verificationContextToken();
        $clock = $this->clock;
        if ($token === null) {
            return [null, null, $clock->now()];
        }

        $store         = $this->contacts;
        $scope         = new VerificationScope(Realm::USER);
        $email         = $input->email();
        $mobile        = $input->mobile();
        $emailValue    = new ContactValue('email', $email->toString());
        $mobileValue   = new ContactValue('mobile', $mobile->toString());
        $emailContact  = $store->slot($token, $scope, $emailValue);
        $mobileContact = $store->slot($token, $scope, $mobileValue);

        return [$emailContact, $mobileContact, $clock->now()];
    }

    /** @param list<ContactInfo> $contacts */
    public function consume(array $contacts, string $accountId, string $actorId, DateTimeImmutable $now): void
    {
        $store = $this->contacts;
        foreach ($contacts as $contact) {
            $contact->consume($accountId, $actorId, $now);
            $store->save($contact);
        }
    }
}
