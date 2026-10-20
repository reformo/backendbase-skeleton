<?php

declare(strict_types=1);

namespace Tests\Shared;

use ArrayIterator;
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
                $this->initializeOccurredOn(new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
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
        self::assertSame($domainEvent, $domainEvents->first());
        self::assertInstanceOf(ArrayIterator::class, $domainEvents->getIterator());
        self::assertEquals(new DateTimeImmutable('2026-10-19T10:00:00.123456Z'), $domainEvent->occurredOn());
        self::assertSame(['exampleId' => 'example-id'], $domainEvent->jsonSerialize());
    }

    #[Test]
    public function itStartsAsAnEmptyNativeCollection(): void
    {
        $domainEvents = new DomainEvents();

        self::assertCount(0, $domainEvents);
        self::assertNull($domainEvents->first());
        self::assertSame([], [...$domainEvents]);
    }
}
