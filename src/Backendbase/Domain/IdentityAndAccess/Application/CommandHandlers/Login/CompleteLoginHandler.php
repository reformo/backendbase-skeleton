<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Login;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication\CompleteLogin;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class CompleteLoginHandler implements CommandHandler
{
    public function __construct(private LoginWorkflow $workflow, private LoginCompletion $completion)
    {
    }

    /** @param CompleteLogin $command */
    public function handle(Command $command): void
    {
        $currentWorkflow   = $this->workflow;
        $currentCompletion = $this->completion;

        $attempt                 = $command->input();
        [$principal, $expiresAt] = $currentWorkflow->complete($attempt->realm(), $attempt->input());
        $currentCompletion->complete($command->operationId(), $principal, $expiresAt);
    }
}
