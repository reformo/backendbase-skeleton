<?php

declare(strict_types=1);

namespace Tests\Shared\Domain;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Domain\Exception\DomainRecordNotFound;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventTrait;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SharedDomainSupportTest extends TestCase
{
    #[Test]
    public function itRecordsDomainEventsLazily(): void
    {
        $aggregate = new TestAggregate();
        self::assertCount(0, $aggregate->getRecordedEvents());

        $command = new AddEntry(
            '018f0000-0000-7000-8000-000000000001',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
        );
        $event   = new EntryAdded($command, new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        $aggregate->recordEvent($event);

        self::assertCount(1, $aggregate->getRecordedEvents());
        self::assertSame($event, $aggregate->getRecordedEvents()->first());
        self::assertSame('018f0000-0000-7000-8000-000000000001', $event->entryId());
        self::assertSame($command, $event->payload());
    }

    #[Test]
    public function itCreatesTheEventCollectionWhenTheFirstEventIsRecorded(): void
    {
        $aggregate = new TestAggregate();
        $command   = new AddEntry(
            '018f0000-0000-7000-8000-000000000001',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
        );

        $aggregate->recordEvent(new EntryAdded($command, new DateTimeImmutable('2026-10-19T10:00:00.123456Z')));

        self::assertCount(1, $aggregate->getRecordedEvents());
    }

    #[Test]
    public function itIdentifiesAnInternalIntegrationEvent(): void
    {
        $event = new class implements IntegrationEvent {
            use IntegrationEventTrait;

            public function eventName(): string
            {
                return 'Internal';
            }

            public function eventVersion(): string
            {
                return '1.0';
            }

            /** @return array<string, mixed> */
            public function getEventArguments(): array
            {
                return [];
            }
        };

        self::assertFalse($event->isExternal());
    }

    #[Test]
    public function itCreatesPureDomainErrorsWithContext(): void
    {
        $exception = DomainRecordNotFound::create(
            'Record not found.',
            ['resourceId' => 'resource-id'],
        );

        self::assertSame('Record not found.', $exception->getMessage());
        self::assertSame(['resourceId' => 'resource-id'], $exception->context());
        self::assertSame([], DomainRecordNotFound::create('Missing.', null)->context());
    }
}
