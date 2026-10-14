<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Registration;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication\RegisterUser;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class RegisterUserHandler implements CommandHandler
{
    public function __construct(private RegistrationWorkflow $workflow, private IdentityOperationResults $results)
    {
    }

    /** @param RegisterUser $command */
    public function handle(Command $command): void
    {
        $currentWorkflow = $this->workflow;
        $currentResults  = $this->results;

        $data = $currentWorkflow->register($command->operationId(), $command->input());
        $currentResults->put($command->operationId(), new IdentityOperationResult(201, $data));
    }
}
