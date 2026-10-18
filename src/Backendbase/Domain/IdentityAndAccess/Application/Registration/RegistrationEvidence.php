<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Registration\RegistrationPolicy;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;

use function array_filter;
use function array_values;

final readonly class RegistrationEvidence
{
    public function __construct(private RegistrationContacts $contacts, private RegistrationPolicy $policy)
    {
    }

    /** @return array{list<ContactInfo>, DateTimeImmutable} */
    public function verify(RegistrationInput $input): array
    {
        $policy = $this->policy;
        $token  = $input->verificationContextToken();
        if ($token === null && $policy->requiresContext()) {
            throw InvalidUserInput::create('A verification context token is required by the registration policy.');
        }

        $store                                = $this->contacts;
        [$emailContact, $mobileContact, $now] = $store->acquire($input);
        $email                                = $input->email();
        $mobile                               = $input->mobile();
        $emailValue                           = new ContactValue('email', $email->toString());
        $mobileValue                          = new ContactValue('mobile', $mobile->toString());
        $selectedEmail                        = $this->select($emailContact, $emailValue, $input, $now);
        $selectedMobile                       = $this->select($mobileContact, $mobileValue, $input, $now);
        $contacts                             = array_values(array_filter([$selectedEmail, $selectedMobile]));

        return [$contacts, $now];
    }

    /** @param list<ContactInfo> $contacts */
    public function consume(array $contacts, string $accountId, string $actorId, DateTimeImmutable $now): void
    {
        $store = $this->contacts;
        $store->consume($contacts, $accountId, $actorId, $now);
    }

    private function select(ContactInfo|null $contact, ContactValue $expected, RegistrationInput $input, DateTimeImmutable $now): ContactInfo|null
    {
        $policy = $this->policy;
        $type   = $expected->type();
        if ($policy->requires($type)) {
            $contact ??= throw AuthenticationDenied::create('Required contact verification is missing.');
            $this->validate($contact, $input, $now, $expected);

            return $contact;
        }

        if ($contact === null) {
            return null;
        }

        try {
            $this->validate($contact, $input, $now, $expected);

            return $contact;
        } catch (AuthenticationDenied | ContactVerificationExpired | IdentityConflict) {
            return null;
        }
    }

    private function validate(ContactInfo $contact, RegistrationInput $input, DateTimeImmutable $now, ContactValue $expected): void
    {
        $contact->assertFresh($now);
        $token = $input->verificationContextToken() ?? throw AuthenticationDenied::create('The verification context is missing.');
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
