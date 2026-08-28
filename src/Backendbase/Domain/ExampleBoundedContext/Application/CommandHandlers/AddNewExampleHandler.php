<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\DomainEvents\ExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\NewExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\NewExampleAddedPayload;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Override;

readonly class AddNewExampleHandler implements CommandHandler
{
    public function __construct(
        private ExampleWriteRepository $exampleRepository,
        private IntegrationEventTransaction $integrationEventTransaction,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /** @param AddNewExample $command */
    #[Override]
    public function handle(Command $command): void
    {
        $example = Example::create(
            $command->exampleId(),
            $command->type(),
            $command->typeTargetId(),
            $command->group(),
            $command->isActive(),
            $command->key(),
            $command->value(),
            $command->details(),
        );
        $this->integrationEventTransaction->execute(function () use ($command, $example): NewExampleAdded {
            $this->exampleRepository->add($example);
            $domainEvent = new ExampleAdded($command->exampleId(), $command);
            $this->domainEventPublisher->publish($domainEvent);

            return new NewExampleAdded(new NewExampleAddedPayload(
                exampleId: $command->exampleId(),
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
