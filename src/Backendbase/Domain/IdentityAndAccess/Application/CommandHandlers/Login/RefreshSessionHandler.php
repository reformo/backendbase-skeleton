<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Login;

use Backendbase\Domain\IdentityAndAccess\Application\Sessions\SessionLifecycle;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication\RefreshSession;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class RefreshSessionHandler implements CommandHandler
{
    public function __construct(private SessionLifecycle $sessions, private IdentityOperationResults $results)
    {
    }

    /** @param RefreshSession $command */
    public function handle(Command $command): void
    {
        $sessions    = $this->sessions;
        $results     = $this->results;
        $input       = $command->input();
        $realm       = $input->realm();
        $token       = $input->token();
        $session     = $sessions->renew($realm, $token);
        $operationId = $command->operationId();
        $results->put($operationId, new IdentityOperationResult(200, $session));
    }
}
