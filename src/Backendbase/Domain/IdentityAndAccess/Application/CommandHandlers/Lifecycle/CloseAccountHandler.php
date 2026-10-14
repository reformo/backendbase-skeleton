<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Lifecycle;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LifecycleWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Lifecycle\CloseAccount;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class CloseAccountHandler implements CommandHandler
{
    public function __construct(private LifecycleWorkflow $workflow, private LifecycleCompletion $completion)
    {
    }

    /** @param CloseAccount $command */
    public function handle(Command $command): void
    {
        $currentWorkflow   = $this->workflow;
        $currentCompletion = $this->completion;

        $input = $command->input();
        $currentWorkflow->closeAccount($input->principal(), $input->now());
        $currentCompletion->accountClosed($command->operationId(), $input->principal());
    }
}
