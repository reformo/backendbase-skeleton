<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\ExternalIntegrationEventSubscribers\ExampleBoundedContext;

use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\GreetingRequestedPayload;
use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Override;
use UnexpectedValueException;

use const PHP_EOL;

final readonly class GreetingRequestedExternalSubscriber implements ExternalIntegrationEventSubscriber
{
    public const string EVENT_TYPE = 'Example_GreetingRequested_Event';

    public function __construct()
    {
    }

    /** @return list<string> */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [self::EVENT_TYPE];
    }

    #[Override]
    public function handle(EventMessage $message): void
    {
        if (! $message instanceof GreetingRequestedPayload) {
            throw new UnexpectedValueException('An unsupported greeting message was received.');
        }

        echo 'hello ' . $message->fullName() . PHP_EOL;
    }
}
