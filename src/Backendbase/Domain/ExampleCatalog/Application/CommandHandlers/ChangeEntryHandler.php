<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryChanged;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryChangedPayload;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Override;

readonly class ChangeEntryHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'example.change';

    public function __construct(
        private EntryWriteRepository $entryRepository,
        private IntegrationEventTransaction $integrationEventTransaction,
    ) {
    }

    /** @param ChangeEntry $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $this->integrationEventTransaction->execute(
            function () use ($command): EntryChanged {
                $identity = $command->identity();
                $entry    = $this->entryRepository->getActiveByIdentity($identity);
                $isActive = $command->isActive();
                $value    = $command->value();
                $details  = $command->details();
                $entry->change($isActive, $value, $details);
                $this->entryRepository->save($entry);

                return new EntryChanged(new EntryChangedPayload(
                    exampleId: $entry->id(),
                    isActive: $isActive,
                    value: $value,
                    details: $details,
                ));
            },
        );
    }
}
