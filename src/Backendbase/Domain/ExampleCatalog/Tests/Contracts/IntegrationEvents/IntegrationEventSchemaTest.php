<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Contracts\IntegrationEvents;

use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryChanged;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryAddedPayload;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryChangedPayload;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntegrationEventSchemaTest extends TestCase
{
    #[Test]
    public function entryAddedVersionOneHasAnExplicitSchema(): void
    {
        $event           = new EntryAdded(new EntryAddedPayload(
            exampleId: 'example-id',
            type: 'system',
            typeTargetId: null,
            group: 'settings',
            isActive: true,
            key: 'page-size',
            value: '25',
            details: ['source' => 'test'],
        ), new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        $expectedPayload = [
            'exampleId' => 'example-id',
            'command' => [
                'exampleId' => 'example-id',
                'type' => 'system',
                'typeTargetId' => null,
                'group' => 'settings',
                'isActive' => true,
                'key' => 'page-size',
                'value' => '25',
                'details' => ['source' => 'test'],
            ],
        ];

        self::assertSame('1.0', $event->eventVersion());
        self::assertSame($expectedPayload, $event->getEventArguments());
        self::assertSame($expectedPayload, $event->toArray());
    }

    #[Test]
    public function entryChangedVersionOneHasAnExplicitSchema(): void
    {
        $event           = new EntryChanged(new EntryChangedPayload(
            exampleId: 'example-id',
            isActive: false,
            value: '50',
            details: null,
        ), new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        $expectedPayload = [
            'exampleId' => 'example-id',
            'command' => [
                'isActive' => false,
                'value' => '50',
                'details' => null,
                'exampleId' => 'example-id',
            ],
        ];

        self::assertSame('1.0', $event->eventVersion());
        self::assertSame($expectedPayload, $event->getEventArguments());
        self::assertSame($expectedPayload, $event->toArray());
    }
}
