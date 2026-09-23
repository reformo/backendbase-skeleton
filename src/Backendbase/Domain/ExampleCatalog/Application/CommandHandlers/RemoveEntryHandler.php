<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Override;

readonly class RemoveEntryHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'example.remove';

    public function __construct(
        private EntryWriteRepository $entryRepository,
        private IntegrationEventTransaction $integrationEventTransaction,
    ) {
    }

    /** @param RemoveEntry $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $this->integrationEventTransaction->execute(
            function () use ($command): EntryRemoved {
                $identity = $command->identity();
                $entry    = $this->entryRepository->getActiveByIdentity($identity);
                $entry->remove();
                $this->entryRepository->save($entry);

                return new EntryRemoved($entry->id());
            },
        );
    }
}
