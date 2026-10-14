<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class AuthenticationGate
{
    public function __construct(private IdentityStore $store, private AuthenticationTokens $tokens)
    {
    }

    public function verify(Realm $realm, string $token): Principal
    {
        $currentTokens = $this->tokens;

        $principal = $currentTokens->verify($realm, $token);
        $this->confirm($principal);

        return $principal;
    }

    public function confirm(Principal $principal): void
    {
        $currentStore = $this->store;

        $currentStore->transaction(fn () => $this->assertCurrent($principal));
    }

    public function assertCurrent(Principal $principal): void
    {
        $currentStore = $this->store;

        $identity = $principal->identity();
        $account  = $currentStore->account($identity['accountId']);
        $account->assertSessionAllowed($identity['authVersion']);
        $actor = $currentStore->actor($principal->realm(), $identity['actorId']);
        $state = $actor->snapshot();
        $actor->assertSessionAllowed();
        if ($state['accountId'] !== $identity['accountId']) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }
}
