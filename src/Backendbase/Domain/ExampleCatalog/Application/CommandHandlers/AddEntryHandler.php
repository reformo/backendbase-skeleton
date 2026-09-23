<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded as EntryAddedIntegrationEvent;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryAddedPayload;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Override;

readonly class AddEntryHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'example.add';

    public function __construct(
        private EntryWriteRepository $entryRepository,
        private IntegrationEventTransaction $integrationEventTransaction,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /** @param AddEntry $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $entry = Entry::create(
            $command->entryId(),
            $command->type(),
            $command->typeTargetId(),
            $command->group(),
            $command->isActive(),
            $command->key(),
            $command->value(),
            $command->details(),
        );
        $this->integrationEventTransaction->execute(function () use ($command, $entry): EntryAddedIntegrationEvent {
            $this->entryRepository->add($entry);
            $domainEvent = new EntryAdded($command->entryId(), $command);
            $this->domainEventPublisher->publish($domainEvent);

            return new EntryAddedIntegrationEvent(new EntryAddedPayload(
                exampleId: $command->entryId(),
                type: $command->type()->value,
                typeTargetId: $command->typeTargetId(),
                group: $command->group(),
                isActive: $command->isActive(),
                key: $command->key(),
                value: $command->value(),
                details: $command->details(),
            ));
        });
    }
}
