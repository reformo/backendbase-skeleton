<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactService;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Contacts\VerifyContact;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class VerifyContactHandler implements CommandHandler
{
    public function __construct(private ContactService $service, private ContactCompletion $completion)
    {
    }

    /** @param VerifyContact $command */
    public function handle(Command $command): void
    {
        $completion  = $this->completion;
        $operationId = $command->operationId();
        $input       = $command->input();
        $service     = $this->service;
        $completion->complete($operationId, $input, $service);
    }
}
