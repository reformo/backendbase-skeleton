<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Contacts\VerifyContact;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

final readonly class VerifyContactHandler implements CommandHandler
{
    public function __construct(private ContactExecutionServices $services, private IdentityOperationResults $results)
    {
    }

    /** @param VerifyContact $command */
    public function handle(Command $command): void
    {
        $services = $this->services;
        $input    = $command->input();
        $contact  = $services->execute($input);
        $results  = $this->results;
        $data     = $contact->jsonSerialize();
        $results->put($command->operationId(), new IdentityOperationResult(200, $data));
    }
}
