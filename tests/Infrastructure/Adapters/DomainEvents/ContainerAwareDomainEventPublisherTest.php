<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\DomainEvents;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Infrastructure\Adapters\DomainEvents\ContainerAwareDomainEventPublisher;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use UnexpectedValueException;

final class ContainerAwareDomainEventPublisherTest extends TestCase
{
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

        $this->expectException(UnexpectedValueException::class);

        $publisher->publish(new EntryAdded('example-id', $command));
    }
}
