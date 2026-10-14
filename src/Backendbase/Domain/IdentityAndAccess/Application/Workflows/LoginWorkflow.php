<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTools;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;

final readonly class LoginWorkflow
{
    public function __construct(private IdentityStore $store, private AuthenticationTools $tools)
    {
    }

    /** @return array{Principal, DateTimeImmutable} */
    public function authenticate(Realm $realm, LoginInput $input): array
    {
        $currentStore = $this->store;
        $currentTools = $this->tools;

        $principal = $currentStore->transaction(function () use ($realm, $input): Principal {
            $currentStore = $this->store;
            $currentTools = $this->tools;

            $loginName = $input->loginName();
            $actor     = $currentStore->actorByLogin($realm, $loginName);
            if ($actor === null) {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $actor->assertSessionAllowed();
            $state        = $actor->snapshot();
            $account      = $currentStore->account($state['accountId']);
            $accountState = $account->snapshot();
            $account->assertSessionAllowed($accountState['authVersion']);
            $accountId = $account->id();
            $password  = $input->password();
            $currentTools->assertPassword($accountId, $password);
            $actorId           = $actor->id();
            $sessionId         = IdentityId::create();
            $tokenId           = IdentityId::create();
            $sessionIdentifier = $sessionId->toString();
            $tokenIdentifier   = $tokenId->toString();

            return new Principal($realm, ['accountId' => $accountId, 'actorId' => $actorId, 'sid' => $sessionIdentifier, 'jti' => $tokenIdentifier, 'authVersion' => $accountState['authVersion']]);
        });

        return [$principal, $currentTools->sessionExpiresAt()];
    }
}
