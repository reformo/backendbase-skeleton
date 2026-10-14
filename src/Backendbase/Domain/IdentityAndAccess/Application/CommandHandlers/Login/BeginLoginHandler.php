<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Login;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication\BeginLogin;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class BeginLoginHandler implements CommandHandler
{
    public function __construct(private LoginWorkflow $workflow, private IdentityOperationResults $results)
    {
    }

    /** @param BeginLogin $command */
    public function handle(Command $command): void
    {
        $currentWorkflow = $this->workflow;
        $currentResults  = $this->results;

        $attempt = $command->input();
        $data    = $currentWorkflow->begin($command->operationId(), $attempt->realm(), $attempt->input());
        $currentResults->put($command->operationId(), new IdentityOperationResult(200, $data));
    }
}
