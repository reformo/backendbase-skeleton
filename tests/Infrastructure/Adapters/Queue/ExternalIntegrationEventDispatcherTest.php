<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventDispatcher;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Shared\Services\EventManager\EventManager;
use CuyZ\Valinor\Mapper\MappingError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

final class ExternalIntegrationEventDispatcherTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidVersionOnePayloads')]
    #[Test]
    public function itRejectsPayloadsOutsideTheRegisteredVersionSchema(array $payload): void
    {
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('getSubscriber')->willReturn([
            'subscriber' => EntryAddedExternalSubscriber::class,
        ]);
        $eventManager->expects(self::never())->method('dispatchExternalEvent');
        $registry   = new InMemoryExternalIntegrationEventRegistry([
            [
                'eventName' => 'Example_NewExampleAdded_Event',
                'eventVersion' => '1.0',
                'messageFQCN' => EntryAddedMessage::class,
            ],
        ]);
        $dispatcher = new ExternalIntegrationEventDispatcher($eventManager, $registry);

        $this->expectException(MappingError::class);

        $dispatcher->dispatch('Example_NewExampleAdded_Event', '1.0', $payload);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidVersionOnePayloads(): iterable
    {
        $extraField               = self::validVersionOnePayload();
        $extraField['unexpected'] = 'value';

        yield 'extra field' => [$extraField];

        $missingField = self::validVersionOnePayload();
        unset($missingField['command']['details']);

        yield 'missing field' => [$missingField];

        $incorrectType                        = self::validVersionOnePayload();
        $incorrectType['command']['isActive'] = 'true';

        yield 'incorrect scalar type' => [$incorrectType];
    }

    #[Test]
    public function itRejectsAnEventWithoutSubscribers(): void
    {
        $eventManager = $this->createStub(EventManager::class);
        $dispatcher   = new ExternalIntegrationEventDispatcher(
            $eventManager,
            new InMemoryExternalIntegrationEventRegistry([]),
        );

        $this->expectException(UnexpectedValueException::class);

        $dispatcher->dispatch('Missing_Event', '1.0', []);
    }

    #[Test]
    public function itRejectsARegisteredClassThatIsNotAnExternalSubscriber(): void
    {
        $eventManager = $this->createStub(EventManager::class);
        $eventManager->method('getSubscriber')->willReturn(['invalid' => stdClass::class]);
        $dispatcher = new ExternalIntegrationEventDispatcher(
            $eventManager,
            new InMemoryExternalIntegrationEventRegistry([]),
        );

        $this->expectException(UnexpectedValueException::class);

        $dispatcher->dispatch('Invalid_Event', '1.0', []);
    }

    #[Test]
    public function itRejectsANonObjectQueuePayload(): void
    {
        $eventManager = $this->createStub(EventManager::class);
        $eventManager->method('getSubscriber')->willReturn([
            'subscriber' => EntryAddedExternalSubscriber::class,
        ]);
        $dispatcher = new ExternalIntegrationEventDispatcher(
            $eventManager,
            new InMemoryExternalIntegrationEventRegistry([]),
        );

        $this->expectException(UnexpectedValueException::class);

        $dispatcher->dispatch('Invalid_Event', '1.0', 'invalid');
    }

    /** @return array<string, mixed> */
    private static function validVersionOnePayload(): array
    {
        return [
            'exampleId' => 'example-id',
            'command' => [
                'exampleId' => 'example-id',
                'type' => 'system',
                'typeTargetId' => null,
                'group' => 'settings',
                'isActive' => true,
                'key' => 'key',
                'value' => 'value',
                'details' => [],
            ],
        ];
    }
}
