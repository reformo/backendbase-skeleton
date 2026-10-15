<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTools;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Exception\InvalidUserInput;

final readonly class ContactAuthorizer
{
    public function __construct(private IdentityStore $identities, private AuthenticationTools $tools)
    {
    }

    /** @return array{VerificationScope, ContactValue|null} */
    public function resolve(ContactAttempt $input, ContactStore $contacts): array
    {
        $values = $input->values();
        if ($values['realm'] === Realm::USER) {
            return [new VerificationScope(Realm::USER), $values['contact']];
        }

        if ($values['principal'] !== null || $values['login'] !== null) {
            $store = $this->identities;

            return $store->transaction(fn () => $this->authorize($input));
        }

        $id = $values['contactId'] ?? throw InvalidUserInput::create('A contact identifier is required.');

        return $contacts->transaction(static function () use ($contacts, $id, $values): array {
            $contact = $contacts->get($id);
            $state   = $contact->snapshot();
            $scope   = new VerificationScope(Realm::ADMIN, ['accountId' => $state['accountId'], 'actorId' => $state['actorId']]);
            $contact->assertContext($values['token'], $scope);

            return [$scope, null];
        });
    }

    /** @return array{VerificationScope, ContactValue|null} */
    private function authorize(ContactAttempt $input): array
    {
        $values    = $input->values();
        $store     = $this->identities;
        $principal = $values['principal'];
        if ($principal !== null) {
            $principal->assertAdmin();
            $identity = $principal->identity();
            $account  = $store->account($identity['accountId']);
            $account->assertSessionAllowed($identity['authVersion']);
            $actor = $store->actor(Realm::ADMIN, $identity['actorId']);
            $actor->assertSessionAllowed();
            $state = $actor->snapshot();
            if ($state['accountId'] !== $identity['accountId']) {
                throw AuthenticationDenied::create('Contact verification is not permitted.');
            }

            return $this->authorized($input, $state['accountId'], $identity['actorId']);
        }

        $login      = $values['login'] ?? throw AuthenticationDenied::create('Contact verification is not permitted.');
        $identifier = $login->identifier();
        $actor      = $store->actorByIdentifier(Realm::ADMIN, $identifier);
        if ($actor === null) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }

        $actor->assertContactVerificationAllowed();
        $state        = $actor->snapshot();
        $account      = $store->account($state['accountId']);
        $accountState = $account->snapshot();
        $account->assertSessionAllowed($accountState['authVersion']);
        $tools    = $this->tools;
        $password = $login->password();
        $tools->assertPassword($state['accountId'], $password);

        return $this->authorized($input, $state['accountId'], $actor->id());
    }

    /** @return array{VerificationScope, ContactValue|null} */
    private function authorized(ContactAttempt $input, string $accountId, string $actorId): array
    {
        $scope  = new VerificationScope(Realm::ADMIN, ['accountId' => $accountId, 'actorId' => $actorId]);
        $values = $input->values();
        if ($values['action'] !== 'request') {
            return [$scope, null];
        }

        $store   = $this->identities;
        $account = $store->account($accountId);
        $state   = $account->snapshot();
        $type    = $values['contactType'];
        if ($type !== 'email' && $type !== 'mobile') {
            throw InvalidUserInput::create('Select email or mobile.');
        }

        return [$scope, new ContactValue($type, $state[$type])];
    }
}
