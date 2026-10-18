<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
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
    public function __construct(private IdentityStore $identities, private Credentials $credentials)
    {
    }

    /** @return array{VerificationScope, ContactValue|null} */
    public function resolve(ContactAttempt $input, ContactStore $contacts): array
    {
        $values    = $input->values();
        $principal = $values['principal'];
        if ($principal !== null && $principal->realm() !== $values['realm']) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }

        if ($values['principal'] !== null || $values['login'] !== null) {
            $store = $this->identities;

            return $store->transaction(fn () => $this->authorize($input));
        }

        if ($values['realm'] === Realm::USER) {
            return [new VerificationScope(Realm::USER), $values['contact']];
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
            $identity = $principal->identity();
            $account  = $store->account($identity['accountId']);
            $account->assertSessionAllowed($identity['authVersion']);
            $actor = $store->actor($values['realm'], $identity['actorId']);
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
        $credentials = $this->credentials;
        $password    = $login->password();
        $credentials->assertPassword($state['accountId'], $password);

        return $this->authorized($input, $state['accountId'], $actor->id());
    }

    /** @return array{VerificationScope, ContactValue|null} */
    private function authorized(ContactAttempt $input, string $accountId, string $actorId): array
    {
        $values = $input->values();
        $scope  = new VerificationScope($values['realm'], ['accountId' => $accountId, 'actorId' => $actorId]);
        if ($values['action'] !== 'request') {
            return [$scope, null];
        }

        $store   = $this->identities;
        $account = $store->account($accountId);
        $state   = $account->snapshot();
        $type    = $values['contactType'];
        if ($values['realm'] === Realm::USER && $type !== 'email') {
            throw InvalidUserInput::create('Select email for User contact verification after login.');
        }

        if ($type !== 'email' && $type !== 'mobile') {
            throw InvalidUserInput::create('Select email or mobile.');
        }

        return [$scope, new ContactValue($type, $state[$type])];
    }
}
