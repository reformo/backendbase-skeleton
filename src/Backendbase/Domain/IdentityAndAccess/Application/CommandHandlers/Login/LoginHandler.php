<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Login;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication\Login;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class LoginHandler implements CommandHandler
{
    public function __construct(private LoginWorkflow $workflow, private LoginCompletion $completion)
    {
    }

    /** @param Login $command */
    public function handle(Command $command): void
    {
        $currentWorkflow   = $this->workflow;
        $currentCompletion = $this->completion;

        $attempt                 = $command->input();
        $realm                   = $attempt->realm();
        $input                   = $attempt->input();
        $operationId             = $command->operationId();
        [$principal, $expiresAt] = $currentWorkflow->authenticate($realm, $input);
        $currentCompletion->complete($operationId, $principal, $expiresAt);
    }
}
