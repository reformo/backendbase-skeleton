<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Operations;

use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Operations\ApplyOperatorAction;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class ApplyOperatorActionHandler implements CommandHandler
{
    public function __construct(private OperatorWorkflow $workflow, private OperatorCompletion $completion)
    {
    }

    /** @param ApplyOperatorAction $command */
    public function handle(Command $command): void
    {
        $completion = $this->completion;
        $completion->complete($this->workflow, $command);
    }
}
