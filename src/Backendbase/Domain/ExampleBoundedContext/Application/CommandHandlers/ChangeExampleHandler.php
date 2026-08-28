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
    public const string REQUIRED_PRIVILEGE = 'example.change';

    public function __construct(
        private ExampleWriteRepository $exampleRepository,
        private IntegrationEventTransaction $integrationEventTransaction,
    ) {
    }

    /** @param ChangeExample $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $this->integrationEventTransaction->execute(
            function () use ($command): ExampleChanged {
                $identity = $command->identity();
                $example  = $this->exampleRepository->getActiveByIdentity($identity);
                $isActive = $command->isActive();
                $value    = $command->value();
                $details  = $command->details();
                $example->change($isActive, $value, $details);
                $this->exampleRepository->save($example);

                return new ExampleChanged(new ExampleChangedPayload(
                    exampleId: $example->id(),
                    isActive: $isActive,
                    value: $value,
                    details: $details,
                ));
            },
        );
    }
}
