<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleChanged;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\ExampleChangedPayload;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Override;

readonly class ChangeExampleHandler implements CommandHandler
{
    public function __construct(
        private ExampleWriteRepository $exampleRepository,
        private IntegrationEventTransaction $integrationEventTransaction,
    ) {
    }

    /** @param ChangeExample $command */
    #[Override]
    public function handle(Command $command): void
    {
        $event = new ExampleChanged(new ExampleChangedPayload(
            exampleId: $command->exampleId(),
            isActive: $command->isActive(),
            value: $command->value(),
            details: $command->details(),
        ));
        $this->integrationEventTransaction->execute(
            $event,
            function () use ($command): void {
                $example = $this->exampleRepository->getActive($command->exampleId());
                $example->change($command->isActive(), $command->value(), $command->details());
                $this->exampleRepository->save($example);
            },
        );
    }
}
