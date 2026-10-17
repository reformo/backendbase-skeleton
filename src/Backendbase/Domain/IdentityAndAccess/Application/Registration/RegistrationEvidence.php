<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

use function sort;

use const SORT_STRING;

final readonly class RegistrationEvidence
{
    public function __construct(private ContactStore $contacts, private Clock $clock)
    {
    }

    /** @return array{list<ContactInfo>, DateTimeImmutable} */
    public function verify(RegistrationInput $input): array
    {
        $proof = $input->evidence();
        $ids   = [$proof['emailContactInfoId'], $proof['mobileContactInfoId']];
        sort($ids, SORT_STRING);
        if ($ids[0] === $ids[1]) {
            throw AuthenticationDenied::create('Both contacts require verification.');
        }

        $store         = $this->contacts;
        $firstContact  = $store->get($ids[0]);
        $secondContact = $store->get($ids[1]);
        $clock         = $this->clock;
        $now           = $clock->now();
        $email         = $input->email();
        $mobile        = $input->mobile();
        $expected      = ['email' => $email->toString(), 'mobile' => $mobile->toString()];
        $this->validate($firstContact, $input, $now, $expected);
        $this->validate($secondContact, $input, $now, $expected);

        return [[$firstContact, $secondContact], $now];
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

    /** @param array{email: string, mobile: string} $expected */
    private function validate(ContactInfo $contact, RegistrationInput $input, DateTimeImmutable $now, array $expected): void
    {
        $contact->assertFresh($now);
        $proof = $input->evidence();
        $contact->assertContext($proof['token'], new VerificationScope(Realm::USER));
        $state = $contact->snapshot();
        $id    = $contact->id();
        $type  = $id === $proof['emailContactInfoId'] ? 'email' : 'mobile';
        if ($state['contactType'] !== $type || $state['contactValue'] !== $expected[$type]) {
            throw AuthenticationDenied::create('The registration contacts do not match their verification evidence.');
        }
    }
}
