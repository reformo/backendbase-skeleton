<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class LoginAuthentication
{
    public function __construct(private LoginWorkflow $workflow)
    {
    }

    public function authenticate(Realm $realm, LoginInput $input): Principal
    {
        $workflow = $this->workflow;

        return $workflow->authenticate($realm, $input);
    }
}
