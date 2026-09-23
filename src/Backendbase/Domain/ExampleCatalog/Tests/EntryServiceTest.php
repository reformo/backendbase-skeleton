<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryReadRepository as MemoryEntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryStore;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryWriteRepository as MemoryEntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\ServiceProvider;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareCommandBus;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareQueryBus;
use Backendbase\Infrastructure\Adapters\CQRS\RegistryHandlerResolver;
use Backendbase\Infrastructure\Adapters\DomainEvents\ContainerAwareDomainEventPublisher;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Primitives\Pagination;
use DI\Container;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

use function count;

/** @codeCoverageIgnore */
class EntryServiceTest extends TestCase
{
    private QueryBus $queryBus;
    private CommandBus $commandBus;

    private ContainerInterface $container;
    private TestHandler $domainEventLogHandler;

    protected function setUp(): void
    {
        $this->container = new Container();
        $store           = new EntryStore();
        $this->container->set(EntryReadRepository::class, new MemoryEntryReadRepository($store));
        $this->container->set(EntryWriteRepository::class, new MemoryEntryWriteRepository($store));

        $logger                      = new Logger('domain-event-test');
        $this->domainEventLogHandler = new TestHandler();
        $logger->pushHandler($this->domainEventLogHandler);
        $this->container->set(LoggerInterface::class, $logger);
        $this->container->set(
            DomainEventPublisher::class,
            new ContainerAwareDomainEventPublisher($this->container),
        );

        $transaction = self::createStub(IntegrationEventTransaction::class);
        $transaction->method('execute')->willReturnCallback(
            static function (callable $mutation): void {
                $event = $mutation();
                self::assertInstanceOf(IntegrationEvent::class, $event);
            },
        );
        $this->container->set(IntegrationEventTransaction::class, $transaction);

        $resolver         = new RegistryHandlerResolver(ServiceProvider::getHandlers());
        $this->queryBus   = new ContainerAwareQueryBus($this->container, $resolver);
        $this->commandBus = new ContainerAwareCommandBus($this->container, $resolver);
    }

    #[Test]
    public function testItForEmptyGroups(): void
    {
        $query = new GetEntryGroupsByType(EntryType::SYSTEM, null);

        $result = $this->queryBus->handle($query);
        $this->assertSame([], $result->items());
        $this->assertSame(0, $result->total());
    }

    #[Test]
    public function testItForEmptyItemsForGroups(): void
    {
        $query = new GetEntriesByGroup(EntryType::SYSTEM, null, 'settings', new Pagination(10, 1));

        $result = $this->queryBus->handle($query);
        $this->assertInstanceOf(EntryPage::class, $result);
        $this->assertSame(0, $result->total());
        $this->assertSame([], $result->items());
    }

    #[Test]
    public function testItForSuccessfullyAddItemAndGetItsDetails(): void
    {
        $entryId = Uuid::uuid7()->toString();
        $command = new AddEntry(
            $entryId,
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'max-list-item',
            '10',
            new Acl(['full-privileges']),
        );

        $this->commandBus->handle($command);

        $query = new GetEntryGroupsByType(EntryType::SYSTEM, null);

        $result = $this->queryBus->handle($query);
        $this->assertSame(['settings'], $result->items());

        $query = new GetEntriesByGroup(EntryType::SYSTEM, null, 'settings', new Pagination(10, 1));

        $result = $this->queryBus->handle($query);
        $this->assertSame(1, $result->total());
        $this->assertSame($entryId, $result->items()[0]->uuid());

        $domainEventRecords = $this->domainEventLogHandler->getRecords();
        $this->assertCount(1, $domainEventRecords);
        $this->assertSame('EntryAddedListener', $domainEventRecords[0]->message);
        $this->assertSame(EntryAdded::class, $domainEventRecords[0]->context['eventName']);
        $this->assertNotEmpty($domainEventRecords[0]->context['occurredOn']);
        $arguments = $domainEventRecords[0]->context['arguments'];
        $this->assertIsArray($arguments);
        $this->assertSame($entryId, $arguments['exampleId'] ?? null);

        $entryId2 = Uuid::uuid7()->toString();
        $command  = new AddEntry(
            $entryId2,
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'min-list-item',
            '2',
            new Acl(['full-privileges']),
        );

        $this->commandBus->handle($command);
        $query = new GetEntriesByGroup(EntryType::SYSTEM, null, 'settings', new Pagination(10, 1));

        $result = $this->queryBus->handle($query);
        $this->assertSame(2, $result->total());
        $this->assertSame($entryId2, $result->items()[1]->uuid());
        $this->assertSame(2, count($result->items()));
        $this->assertCount(2, $this->domainEventLogHandler->getRecords());
    }
}
