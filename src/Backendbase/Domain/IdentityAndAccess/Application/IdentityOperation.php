<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetIdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;

final readonly class IdentityOperation
{
    public function __construct(private CommandBus $commands, private QueryBus $queries)
    {
    }

    public function execute(IdentityCommand $command): IdentityOperationResult
    {
        $commands = $this->commands;
        $commands->handle($command);
        $queries = $this->queries;

        return $queries->handle(new GetIdentityOperation($command->operationId()));
    }
}
