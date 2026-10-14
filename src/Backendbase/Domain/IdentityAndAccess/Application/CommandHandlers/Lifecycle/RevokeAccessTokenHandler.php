<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Lifecycle;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LifecycleWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Lifecycle\RevokeAccessToken;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class RevokeAccessTokenHandler implements CommandHandler
{
    public function __construct(private LifecycleWorkflow $workflow, private LifecycleCompletion $completion)
    {
    }

    /** @param RevokeAccessToken $command */
    public function handle(Command $command): void
    {
        $currentWorkflow   = $this->workflow;
        $currentCompletion = $this->completion;

        $currentWorkflow->authorizeLogout($command->principal());
        $currentCompletion->tokenRevoked($command->operationId(), $command->principal());
    }
}
