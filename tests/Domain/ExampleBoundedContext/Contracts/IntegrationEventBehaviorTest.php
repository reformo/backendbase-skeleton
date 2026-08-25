<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Contracts;

use Backendbase\Domain\ExampleBoundedContext\Application\DomainEventListener\ExampleAddedListener;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleChanged;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleRemoved;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\NewExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\ExampleChangedPayload;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\NewExampleAddedPayload;
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
        $changedPayload = new ExampleChangedPayload('example-id', false, 'changed', ['unit' => 'rows']);
        $changed        = new ExampleChanged($changedPayload);
        self::assertSame('Example_ExampleChanged', $changed->eventName());
        self::assertSame('example-id', $changed->exampleId());
        self::assertSame($changedPayload->toArray(), $changedPayload->jsonSerialize());

        $addedPayload = new NewExampleAddedPayload(
            'example-id',
            'system',
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $added        = new NewExampleAdded($addedPayload);
        self::assertSame('example-id', $added->exampleId());
        self::assertSame($addedPayload->toArray(), $addedPayload->jsonSerialize());

        self::assertSame('example-id', (new ExampleRemoved('example-id'))->exampleId());
    }

    #[Test]
    public function itRejectsAnUnexpectedDomainEvent(): void
    {
        $listener = new ExampleAddedListener(new NullLogger());

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
