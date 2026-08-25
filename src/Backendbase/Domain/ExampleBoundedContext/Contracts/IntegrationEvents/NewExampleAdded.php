<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents;

use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\NewExampleAddedPayload;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventTrait;
use Backendbase\Shared\Helpers\DateTimeImmutable;

final class NewExampleAdded implements IntegrationEvent
{
    public const string EVENT_VERSION    = '1.0';
    public const bool IS_MESSAGING_EVENT = true;
    use IntegrationEventTrait;

    public const string EVENT_TYPE = 'Example_NewExampleAdded';

    public function __construct(private readonly NewExampleAddedPayload $payload)
    {
        $this->occurredOn = DateTimeImmutable::create();
    }

    public function eventName(): string
    {
        return self::EVENT_TYPE;
    }

    public function eventVersion(): string
    {
        return self::EVENT_VERSION;
    }

    /** @return array<string, mixed> */
    public function getEventArguments(): array
    {
        return $this->payload->toArray();
    }

    public function exampleId(): string
    {
        return $this->payload->exampleId();
    }
}
