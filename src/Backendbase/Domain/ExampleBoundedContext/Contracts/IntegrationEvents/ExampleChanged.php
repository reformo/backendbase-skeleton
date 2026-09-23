<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents;

use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\ExampleChangedPayload;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventTrait;
use Backendbase\Shared\Helpers\DateTimeImmutable;

final class ExampleChanged implements IntegrationEvent
{
    public const string EVENT_VERSION   = '1.0';
    public const bool DELIVER_VIA_QUEUE = true;
    use IntegrationEventTrait;

    public const string EVENT_TYPE = 'Example_ExampleChanged';

    public function __construct(private readonly ExampleChangedPayload $payload)
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
