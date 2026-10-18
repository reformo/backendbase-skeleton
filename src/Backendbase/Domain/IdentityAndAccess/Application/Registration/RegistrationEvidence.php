<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

final readonly class RegistrationEvidence
{
    public function __construct(private ContactStore $contacts, private Clock $clock)
    {
    }

    /** @return array{list<ContactInfo>, DateTimeImmutable} */
    public function verify(RegistrationInput $input): array
    {
        $store         = $this->contacts;
        $token         = $input->verificationContextToken();
        $scope         = new VerificationScope(Realm::USER);
        $email         = $input->email();
        $mobile        = $input->mobile();
        $emailValue    = new ContactValue('email', $email->toString());
        $mobileValue   = new ContactValue('mobile', $mobile->toString());
        $emailContact  = $store->slot($token, $scope, $emailValue);
        $mobileContact = $store->slot($token, $scope, $mobileValue)
            ?? throw AuthenticationDenied::create('Mobile verification is required.');
        $clock         = $this->clock;
        $now           = $clock->now();
        $this->validate($mobileContact, $input, $now, $mobileValue);
        $contacts = [$mobileContact];
        if ($emailContact !== null && $this->acceptEmail($emailContact, $input, $now, $emailValue)) {
            $contacts[] = $emailContact;
        }

        return [$contacts, $now];
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

    private function acceptEmail(ContactInfo $contact, RegistrationInput $input, DateTimeImmutable $now, ContactValue $expected): bool
    {
        try {
            $this->validate($contact, $input, $now, $expected);

            return true;
        } catch (AuthenticationDenied | ContactVerificationExpired | IdentityConflict) {
            return false;
        }
    }

    private function validate(ContactInfo $contact, RegistrationInput $input, DateTimeImmutable $now, ContactValue $expected): void
    {
        $contact->assertFresh($now);
        $token = $input->verificationContextToken();
        $contact->assertContext($token, new VerificationScope(Realm::USER));
        $value         = $contact->contact();
        $expectedType  = $expected->type();
        $expectedValue = $expected->value();
        $type          = $value->type();
        $contactValue  = $value->value();
        if ($type !== $expectedType || $contactValue !== $expectedValue) {
            throw AuthenticationDenied::create('The registration contacts do not match their verification evidence.');
        }
    }
}
