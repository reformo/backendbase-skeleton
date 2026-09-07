<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Contracts\IntegrationEvents;

use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleChanged;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\NewExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\ExampleChangedPayload;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\NewExampleAddedPayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntegrationEventSchemaTest extends TestCase
{
    #[Test]
    public function newExampleAddedVersionOneHasAnExplicitSchema(): void
    {
        $event           = new NewExampleAdded(new NewExampleAddedPayload(
            exampleId: 'example-id',
            type: 'system',
            typeTargetId: null,
            group: 'settings',
            isActive: true,
            key: 'page-size',
            value: '25',
            details: ['source' => 'test'],
        ));
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
    public function exampleChangedVersionOneHasAnExplicitSchema(): void
    {
        $event           = new ExampleChanged(new ExampleChangedPayload(
            exampleId: 'example-id',
            isActive: false,
            value: '50',
            details: null,
        ));
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
