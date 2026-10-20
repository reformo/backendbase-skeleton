<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Login;

use Backendbase\Domain\IdentityAndAccess\Application\Sessions\SessionLifecycle;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;

final readonly class LoginCompletion
{
    public function __construct(private SessionLifecycle $sessions, private IdentityOperationResults $results)
    {
    }

    public function complete(string $operationId, Principal $principal): void
    {
        $currentSessions = $this->sessions;
        $currentResults  = $this->results;

        $session = $currentSessions->start($principal);
        $currentResults->put($operationId, new IdentityOperationResult(200, $session));
    }
}
