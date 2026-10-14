<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Enrollment;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\EnrollmentWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication\CompleteTotpEnrollment;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class CompleteTotpEnrollmentHandler implements CommandHandler
{
    public function __construct(private EnrollmentWorkflow $workflow, private IdentityOperationResults $results)
    {
    }

    /** @param CompleteTotpEnrollment $command */
    public function handle(Command $command): void
    {
        $currentWorkflow = $this->workflow;
        $currentResults  = $this->results;

        $attempt = $command->input();
        $currentWorkflow->complete($attempt->realm(), $attempt->input());
        $data = ['completed' => true];
        $currentResults->put($command->operationId(), new IdentityOperationResult(200, $data));
    }
}
