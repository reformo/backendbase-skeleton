<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleReadRepository as MemoryExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleStore;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleWriteRepository as MemoryExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\DomainEvents\ExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExamplePage;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareCommandBus;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareQueryBus;
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
class ExampleServiceTest extends TestCase
{
    private QueryBus $queryBus;
    private CommandBus $commandBus;

    private ContainerInterface $container;
    private TestHandler $domainEventLogHandler;

    protected function setUp(): void
    {
        $this->container = new Container();
        $store           = new ExampleStore();
        $this->container->set(ExampleReadRepository::class, new MemoryExampleReadRepository($store));
        $this->container->set(ExampleWriteRepository::class, new MemoryExampleWriteRepository($store));

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

        $this->queryBus   = new ContainerAwareQueryBus($this->container);
        $this->commandBus = new ContainerAwareCommandBus($this->container);
    }

    #[Test]
    public function testItForEmptyGroups(): void
    {
        $query = new GetExampleGroupsByType(ExampleType::SYSTEM, null);

        $result = $this->queryBus->handle($query);
        $this->assertSame([], $result->items());
        $this->assertSame(0, $result->total());
    }

    #[Test]
    public function testItForEmptyItemsForGroups(): void
    {
        $query = new GetExamplesByGroup(ExampleType::SYSTEM, null, 'settings', new Pagination(10, 1));

        $result = $this->queryBus->handle($query);
        $this->assertInstanceOf(ExamplePage::class, $result);
        $this->assertSame(0, $result->total());
        $this->assertSame([], $result->items());
    }

    #[Test]
    public function testItForSuccessfullyAddItemAndGetItsDetails(): void
    {
        $exampleId = Uuid::uuid7()->toString();
        $command   = new AddNewExample(
            $exampleId,
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'max-list-item',
            '10',
            new Acl(['full-privileges']),
        );

        $this->commandBus->handle($command);

        $query = new GetExampleGroupsByType(ExampleType::SYSTEM, null);

        $result = $this->queryBus->handle($query);
        $this->assertSame(['settings'], $result->items());

        $query = new GetExamplesByGroup(ExampleType::SYSTEM, null, 'settings', new Pagination(10, 1));

        $result = $this->queryBus->handle($query);
        $this->assertSame(1, $result->total());
        $this->assertSame($exampleId, $result->items()[0]->uuid());

        $domainEventRecords = $this->domainEventLogHandler->getRecords();
        $this->assertCount(1, $domainEventRecords);
        $this->assertSame('ExampleAddedListener', $domainEventRecords[0]->message);
        $this->assertSame(ExampleAdded::class, $domainEventRecords[0]->context['eventName']);
        $this->assertNotEmpty($domainEventRecords[0]->context['occurredOn']);
        $arguments = $domainEventRecords[0]->context['arguments'];
        $this->assertIsArray($arguments);
        $this->assertSame($exampleId, $arguments['exampleId'] ?? null);

        $exampleId2 = Uuid::uuid7()->toString();
        $command    = new AddNewExample(
            $exampleId2,
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'min-list-item',
            '2',
            new Acl(['full-privileges']),
        );

        $this->commandBus->handle($command);
        $query = new GetExamplesByGroup(ExampleType::SYSTEM, null, 'settings', new Pagination(10, 1));

        $result = $this->queryBus->handle($query);
        $this->assertSame(2, $result->total());
        $this->assertSame($exampleId2, $result->items()[1]->uuid());
        $this->assertSame(2, count($result->items()));
        $this->assertCount(2, $this->domainEventLogHandler->getRecords());
    }
}
