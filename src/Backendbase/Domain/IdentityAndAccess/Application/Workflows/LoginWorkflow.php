<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class LoginWorkflow
{
    public function __construct(private IdentityStore $store, private Credentials $credentials)
    {
    }

    public function authenticate(Realm $realm, LoginInput $input): Principal
    {
        $currentStore = $this->store;

        return $currentStore->transaction(function () use ($realm, $input): Principal {
            $currentStore = $this->store;

            $identifier = $input->identifier();
            $actor      = $currentStore->actorByIdentifier($realm, $identifier);
            if ($actor === null) {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $actor->assertSessionAllowed();
            $reference   = $actor->accountReference();
            $accountId   = $reference->toString();
            $account     = $currentStore->account($accountId);
            $principal   = $account->startSession($realm, $actor);
            $password    = $input->password();
            $credentials = $this->credentials;
            $credentials->assertPassword($accountId, $password);

            return $principal;
        });
    }
}
