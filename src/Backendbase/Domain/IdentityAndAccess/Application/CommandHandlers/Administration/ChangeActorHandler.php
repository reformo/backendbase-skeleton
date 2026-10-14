<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Administration;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\AdministrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Administration\ChangeActor;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class ChangeActorHandler implements CommandHandler
{
    public function __construct(private AdministrationWorkflow $workflow, private IdentityOperationResults $results)
    {
    }

    /** @param ChangeActor $command */
    public function handle(Command $command): void
    {
        $currentWorkflow = $this->workflow;
        $currentResults  = $this->results;

        $input = $command->input();
        $currentWorkflow->changeActor($input->principal(), $input->input(), $command->now());
        $data = ['completed' => true];
        $currentResults->put($command->operationId(), new IdentityOperationResult(200, $data));
    }
}
