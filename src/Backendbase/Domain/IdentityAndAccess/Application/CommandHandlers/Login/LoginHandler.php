<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Login;

use Backendbase\Domain\IdentityAndAccess\Application\LoginAuthentication;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication\Login;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class LoginHandler implements CommandHandler
{
    public function __construct(private LoginAuthentication $authentication, private LoginCompletion $completion)
    {
    }

    /** @param Login $command */
    public function handle(Command $command): void
    {
        $authentication    = $this->authentication;
        $currentCompletion = $this->completion;

        $attempt     = $command->input();
        $realm       = $attempt->realm();
        $input       = $attempt->input();
        $operationId = $command->operationId();
        $principal   = $authentication->authenticate($realm, $input);
        $currentCompletion->complete($operationId, $principal);
    }
}
