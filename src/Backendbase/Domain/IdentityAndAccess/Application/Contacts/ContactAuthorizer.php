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
        $principal = $input->principal;
        $realm     = $input->realm;
        $login     = $input->login;
        if ($principal !== null && $principal->realm() !== $realm) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }

        if ($principal !== null || $login !== null) {
            $store = $this->identities;

            return $store->transaction(fn () => $this->authorize($input));
        }

        if ($realm === Realm::USER) {
            return [new VerificationScope(Realm::USER), $input->contact];
        }

        $token = $input->token;
        $id    = $input->contactId ?? throw InvalidUserInput::create('A contact identifier is required.');

        return $contacts->transaction(static function () use ($contacts, $id, $token): array {
            $contact = $contacts->get($id);
            $state   = $contact->snapshot();
            $scope   = VerificationScope::forContact(Realm::ADMIN, $state);
            $contact->assertContext($token, $scope);

            return [$scope, null];
        });
    }

    /** @return array{VerificationScope, ContactValue|null} */
    private function authorize(ContactAttempt $input): array
    {
        $store     = $this->identities;
        $principal = $input->principal;
        $realm     = $input->realm;
        if ($principal !== null) {
            $identity = $principal->identity();
            $account  = $store->account($identity['accountId']);
            $account->assertSessionAllowed($identity['authVersion'], $realm);
            $actor = $store->actor($realm, $identity['actorId']);
            $actor->assertSessionAllowed();
            $actor->assertContactOwner($identity['accountId']);

            return $this->authorized($input, $identity['accountId'], $identity['actorId']);
        }

        $login      = $input->login ?? throw AuthenticationDenied::create('Contact verification is not permitted.');
        $identifier = $login->identifier();
        $actor      = $store->actorByIdentifier(Realm::ADMIN, $identifier);
        if ($actor === null) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }

        $actor->assertContactVerificationAllowed();
        $reference = $actor->accountReference();
        $accountId = $reference->toString();
        $account   = $store->account($accountId);
        $account->assertEligible(Realm::ADMIN);
        $credentials = $this->credentials;
        $password    = $login->password();
        $credentials->assertPassword($accountId, $password);

        $actorId = $actor->id();

        return $this->authorized($input, $accountId, $actorId);
    }

    /** @return array{VerificationScope, ContactValue|null} */
    private function authorized(ContactAttempt $input, string $accountId, string $actorId): array
    {
        $realm       = $input->realm;
        $action      = $input->action;
        $contactType = $input->contactType;
        $scope       = new VerificationScope($realm, ['accountId' => $accountId, 'actorId' => $actorId]);
        if ($action !== 'request') {
            return [$scope, null];
        }

        $store   = $this->identities;
        $account = $store->account($accountId);
        $contact = $account->contactFor($realm, $contactType);

        return [$scope, $contact];
    }
}
