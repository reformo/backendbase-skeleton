<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Contracts;

use Backendbase\Domain\ExampleCatalog\Application\DomainEventListener\EntryAddedListener;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryChanged;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryAddedPayload;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryChangedPayload;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use UnexpectedValueException;

final class IntegrationEventBehaviorTest extends TestCase
{
    #[Test]
    public function itExposesIntegrationEventPayloads(): void
    {
        $changedPayload = new EntryChangedPayload('example-id', false, 'changed', ['unit' => 'rows']);
        $changed        = new EntryChanged($changedPayload);
        self::assertSame('Example_ExampleChanged', $changed->eventName());
        self::assertTrue($changed->isExternal());
        self::assertSame('example-id', $changed->entryId());
        self::assertSame($changedPayload->toArray(), $changedPayload->jsonSerialize());

        $addedPayload = new EntryAddedPayload(
            'example-id',
            'system',
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $added        = new EntryAdded($addedPayload);
        self::assertSame('example-id', $added->entryId());
        self::assertSame($addedPayload->toArray(), $addedPayload->jsonSerialize());

        self::assertSame('example-id', (new EntryRemoved('example-id'))->entryId());
    }

    #[Test]
    public function itRejectsAnUnexpectedDomainEvent(): void
    {
        $listener = new EntryAddedListener(new NullLogger());

        $this->expectException(UnexpectedValueException::class);
        $listener->handle(new class implements DomainEvent {
            use DomainEventTrait;

            public function __construct()
            {
                $this->initializeOccurredOn();
            }

            public function eventName(): string
            {
                return 'Unexpected';
            }

            /** @return array<string, mixed> */
            public function getEventArguments(): array
            {
                return [];
            }
        });
    }
}
