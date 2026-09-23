<?php

declare(strict_types=1);

namespace Tests\Shared\Domain;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Shared\Domain\Exception\DomainRecordNotFound;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventTrait;
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
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
            new Acl(['full-privileges']),
        );
        $event   = new EntryAdded('example-id', $command);
        $aggregate->recordEvent($event);

        self::assertCount(1, $aggregate->getRecordedEvents());
        self::assertSame($event, $aggregate->getRecordedEvents()->first());
        self::assertSame('example-id', $event->entryId());
        self::assertSame($command, $event->payload());
    }

    #[Test]
    public function itCreatesTheEventCollectionWhenTheFirstEventIsRecorded(): void
    {
        $aggregate = new TestAggregate();
        $command   = new AddEntry(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
            new Acl(['full-privileges']),
        );

        $aggregate->recordEvent(new EntryAdded('example-id', $command));

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
