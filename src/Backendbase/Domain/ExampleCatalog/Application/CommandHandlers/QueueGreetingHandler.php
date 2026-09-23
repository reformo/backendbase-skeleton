<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\QueueGreeting;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\GreetingRequested;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\GreetingRequestedPayload;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Override;

final readonly class QueueGreetingHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'example.add';

    public function __construct(private IntegrationEventTransaction $integrationEventTransaction)
    {
    }

    /** @param QueueGreeting $command */
    #[Override]
    public function handle(Command $command): void
    {
        $accessControl = $command->accessControl();
        $accessControl->isAllowed(self::REQUIRED_PRIVILEGE);

        $this->integrationEventTransaction->execute(
            static fn (): GreetingRequested => new GreetingRequested(
                new GreetingRequestedPayload($command->fullName()),
            ),
        );
    }
}
