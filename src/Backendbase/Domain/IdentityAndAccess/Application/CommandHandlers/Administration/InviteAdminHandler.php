<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Administration;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\AdministrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Administration\InviteAdmin;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class InviteAdminHandler implements CommandHandler
{
    public function __construct(private AdministrationWorkflow $workflow, private IdentityOperationResults $results)
    {
    }

    /** @param InviteAdmin $command */
    public function handle(Command $command): void
    {
        $currentWorkflow = $this->workflow;
        $currentResults  = $this->results;

        $input = $command->input();
        $data  = $currentWorkflow->invite($input->principal(), $input->input(), $command->now());
        $currentResults->put($command->operationId(), new IdentityOperationResult(201, $data));
    }
}
