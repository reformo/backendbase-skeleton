<?php

declare(strict_types=1);

namespace Tests\Shared\Domain;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\DomainEvents\ExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Domain\ContainerAwareDomainEventPublisher;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventTrait;
use Backendbase\Shared\Domain\Exception\DomainRecordNotFoundExceptionProblemDetails;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use UnexpectedValueException;

final class SharedDomainSupportTest extends TestCase
{
    #[Test]
    public function itRecordsDomainEventsLazily(): void
    {
        $aggregate = new TestAggregate();
        self::assertCount(0, $aggregate->getRecordedEvents());

        $command = new AddNewExample(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
        );
        $event   = new ExampleAdded('example-id', $command);
        $aggregate->recordEvent($event);

        self::assertCount(1, $aggregate->getRecordedEvents());
        self::assertSame($event, $aggregate->getRecordedEvents()->first());
        self::assertSame('example-id', $event->exampleId());
        self::assertSame($command, $event->payload());
    }

    #[Test]
    public function itCreatesTheEventCollectionWhenTheFirstEventIsRecorded(): void
    {
        $aggregate = new TestAggregate();
        $command   = new AddNewExample(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
        );

        $aggregate->recordEvent(new ExampleAdded('example-id', $command));

        self::assertCount(1, $aggregate->getRecordedEvents());
    }

    #[Test]
    public function itRejectsDomainEventsWithoutAListenerAttribute(): void
    {
        $publisher = new ContainerAwareDomainEventPublisher(
            $this->createStub(ContainerInterface::class),
        );
        $event     = new class implements DomainEvent {
            use DomainEventTrait;

            public function __construct()
            {
                $this->initializeOccurredOn();
            }

            public function eventName(): string
            {
                return 'Unregistered';
            }

            /** @return array<string, mixed> */
            public function getEventArguments(): array
            {
                return [];
            }
        };

        $this->expectException(UnexpectedValueException::class);

        $publisher->publish($event);
    }

    #[Test]
    public function itRejectsAContainerEntryThatIsNotADomainEventListener(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new stdClass());
        $publisher = new ContainerAwareDomainEventPublisher($container);
        $command   = new AddNewExample(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
        );

        $this->expectException(UnexpectedValueException::class);

        $publisher->publish(new ExampleAdded('example-id', $command));
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
    public function itSerializesProblemDetailsWithAdditionalData(): void
    {
        $exception = DomainRecordNotFoundExceptionProblemDetails::create(
            'Record not found.',
            ['resourceId' => 'resource-id'],
        );

        self::assertSame(404, $exception->getStatus());
        self::assertSame('about:blank', $exception->getType());
        self::assertSame('domain/not-found', $exception->getErrorCode());
        self::assertSame('NotFound', $exception->getTitle());
        self::assertSame('Record not found.', $exception->getDetail());
        self::assertSame(['resourceId' => 'resource-id'], $exception->getAdditionalData());
        self::assertSame([
            'status' => 404,
            'detail' => 'Record not found.',
            'title' => 'NotFound',
            'type' => 'about:blank',
            'resourceId' => 'resource-id',
        ], $exception->toArray());
        self::assertSame($exception->toArray(), $exception->jsonSerialize());

        $withoutAdditionalData = DomainRecordNotFoundExceptionProblemDetails::create('Missing.', null);
        self::assertSame([], $withoutAdditionalData->getAdditionalData());
    }
}
