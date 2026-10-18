<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

final class InMemoryExternalIntegrationEventRegistryTest extends TestCase
{
    #[Test]
    public function itResolvesAMessageContractByEventNameAndVersion(): void
    {
        $registry = new InMemoryExternalIntegrationEventRegistry([
            [
                'event-name' => 'Example_NewExampleAdded_Event',
                'event-version' => '1.0',
                'message-fqcn' => EntryAddedMessage::class,
            ],
        ]);

        self::assertSame(
            EntryAddedMessage::class,
            $registry->messageClass('Example_NewExampleAdded_Event', '1.0'),
        );
    }

    #[Test]
    public function itRejectsAnUnregisteredEventVersion(): void
    {
        $registry = new InMemoryExternalIntegrationEventRegistry([
            [
                'event-name' => 'Example_NewExampleAdded_Event',
                'event-version' => '1.0',
                'message-fqcn' => EntryAddedMessage::class,
            ],
        ]);

        $this->expectException(UnexpectedValueException::class);

        $registry->messageClass('Example_NewExampleAdded_Event', '2.0');
    }

    #[Test]
    public function itRejectsAClassThatIsNotAnEventMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InMemoryExternalIntegrationEventRegistry([
            [
                'event-name' => 'Invalid_Event',
                'event-version' => '1.0',
                'message-fqcn' => stdClass::class,
            ],
        ]);
    }

    #[Test]
    public function itRejectsADuplicateEventContract(): void
    {
        $definition = [
            'event-name' => 'Example_NewExampleAdded_Event',
            'event-version' => '1.0',
            'message-fqcn' => EntryAddedMessage::class,
        ];

        $this->expectException(InvalidArgumentException::class);

        new InMemoryExternalIntegrationEventRegistry([$definition, $definition]);
    }
}
