<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\EntryEventTransaction;
use Backendbase\Domain\ExampleCatalog\Application\EntryWrites;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Override;

readonly class AddEntryHandler implements CommandHandler
{
    public function __construct(private EntryWrites $writes, private EntryEventTransaction $transaction)
    {
    }

    /** @param AddEntry $command */
    #[Override]
    public function handle(Command $command): void
    {
        $writes      = $this->writes;
        $transaction = $this->transaction;
        $work        = static fn () => $writes->add($command);
        $entryId     = $command->entryId();
        $event       = new EntryAdded($entryId, $command);
        $transaction->execute($work, $event);
    }
}
