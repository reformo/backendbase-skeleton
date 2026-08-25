<?php

declare(strict_types=1);

namespace Tests\Shared;

use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEvents;
use Backendbase\Shared\Domain\DomainEventTrait;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DomainEventsTest extends TestCase
{
    #[Test]
    public function itReturnsRecordedEventsAndInitializesTheirOccurrenceTime(): void
    {
        $domainEvent  = new class implements DomainEvent {
            use DomainEventTrait;

            public function __construct()
            {
                $this->initializeOccurredOn();
            }

            public function eventName(): string
            {
                return self::class;
            }

            /** @return array<string, mixed> */
            public function getEventArguments(): array
            {
                return ['exampleId' => 'example-id'];
            }
        };
        $domainEvents = new DomainEvents();

        $domainEvents->add($domainEvent);

        self::assertSame([$domainEvent], $domainEvents->events());
        self::assertInstanceOf(DateTimeImmutable::class, $domainEvent->occurredOn());
        self::assertSame(['exampleId' => 'example-id'], $domainEvent->jsonSerialize());
    }
}
