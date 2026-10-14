<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Lifecycle;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LifecycleWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Lifecycle\Logout;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class LogoutHandler implements CommandHandler
{
    public function __construct(private LifecycleWorkflow $workflow, private LifecycleCompletion $completion)
    {
    }

    /** @param Logout $command */
    public function handle(Command $command): void
    {
        $currentWorkflow   = $this->workflow;
        $currentCompletion = $this->completion;

        $currentWorkflow->authorizeLogout($command->principal());
        $currentCompletion->logout($command->operationId(), $command->principal());
    }
}
