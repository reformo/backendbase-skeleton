<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventTrait;
use Backendbase\Shared\Helpers\DateTimeImmutable;

class ExampleRemoved implements IntegrationEvent
{
    public const string EVENT_VERSION   = '1.0';
    public const bool DELIVER_VIA_QUEUE = true;
    use IntegrationEventTrait;

    public const string EVENT_TYPE = 'Example_ExampleRemoved';

    public function __construct(private readonly string $exampleId)
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
        return [
            'exampleId' => $this->exampleId,
        ];
    }

    public function exampleId(): string
    {
        return $this->exampleId;
    }
}
