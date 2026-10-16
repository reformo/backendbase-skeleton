<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;

final readonly class LoginAuthentication
{
    public function __construct(private LoginWorkflow $workflow, private AuthenticationClock $clock)
    {
    }

    /** @return array{Principal, DateTimeImmutable} */
    public function authenticate(Realm $realm, LoginInput $input): array
    {
        $workflow  = $this->workflow;
        $principal = $workflow->authenticate($realm, $input);
        $clock     = $this->clock;

        return [$principal, $clock->sessionExpiresAt()];
    }
}
