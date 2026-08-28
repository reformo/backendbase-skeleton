<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\RemoveExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleRemoved;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Override;

readonly class RemoveExampleHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'example.remove';

    public function __construct(
        private ExampleWriteRepository $exampleRepository,
        private IntegrationEventTransaction $integrationEventTransaction,
    ) {
    }

    /** @param RemoveExample $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $this->integrationEventTransaction->execute(
            function () use ($command): ExampleRemoved {
                $identity = $command->identity();
                $example  = $this->exampleRepository->getActiveByIdentity($identity);
                $example->remove();
                $this->exampleRepository->save($example);

                return new ExampleRemoved($example->id());
            },
        );
    }
}
