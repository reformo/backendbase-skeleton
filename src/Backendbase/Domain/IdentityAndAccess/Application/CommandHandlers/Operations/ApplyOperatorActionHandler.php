<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Operations;

use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorRuntime;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Operations\ApplyOperatorAction;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class ApplyOperatorActionHandler implements CommandHandler
{
    public function __construct(private OperatorRuntime $runtime, private IdentityOperationResults $results)
    {
    }

    /** @param ApplyOperatorAction $command */
    public function handle(Command $command): void
    {
        $runtime    = $this->runtime;
        $input      = $command->input();
        $apply      = $command->applies();
        $target     = $runtime->execute($input, $apply);
        $results    = $this->results;
        $identifier = $command->operationId();
        $results->put($identifier, new IdentityOperationResult(200, ['targetId' => $target]));
    }
}
