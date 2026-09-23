<?php

declare(strict_types=1);

namespace Tests\Functional;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryStore;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository as EntryReadRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository as EntryWriteRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareCommandBus;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareQueryBus;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Primitives\Pagination;
use DI\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntryLifecycleTest extends TestCase
{
    #[Test]
    public function itRunsTheCompleteEntryLifecycleThroughTheBuses(): void
    {
        $container     = $this->container();
        $commandBus    = new ContainerAwareCommandBus($container);
        $queryBus      = new ContainerAwareQueryBus($container);
        $accessControl = new Acl(['full-privileges']);

        $commandBus->handle(new AddEntry(
            'first-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'first-key',
            'first-value',
            $accessControl,
            ['unit' => 'items'],
        ));
        $commandBus->handle(new AddEntry(
            'second-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'second-key',
            'second-value',
            $accessControl,
        ));
        $commandBus->handle(new AddEntry(
            'user-id',
            EntryType::USER,
            42,
            'preferences',
            true,
            'theme',
            'dark',
            $accessControl,
        ));

        $groupPage = $queryBus->handle(new GetEntryGroupsByType(EntryType::SYSTEM, null));
        self::assertSame(['settings'], $groupPage->items());
        self::assertSame(
            'first-id',
            $queryBus->handle(new GetEntryIdByCriteria(
                EntryType::SYSTEM,
                null,
                'settings',
                'first-key',
            )),
        );

        $change = new ChangeEntry(new EntryIdentity(
            EntryType::SYSTEM,
            null,
            'settings',
            'first-key',
        ), $accessControl);
        $change->setIsActive(false)->setValue('changed')->setDetails(['unit' => 'rows']);
        $commandBus->handle($change);

        $details = $queryBus->handle(new GetEntryByCriteria(
            EntryType::SYSTEM,
            null,
            'settings',
            'first-key',
        ));
        self::assertNotNull($details);
        self::assertSame('first-id', $details->uuid());
        self::assertSame(EntryType::SYSTEM, $details->type());
        self::assertNull($details->typeTargetId());
        self::assertSame('settings', $details->group());
        self::assertSame('first-key', $details->lookupKey());
        self::assertSame('changed', $details->lookupValue());
        self::assertSame(['unit' => 'rows'], $details->details());
        self::assertFalse($details->isActive());
        self::assertGreaterThanOrEqual($details->createdAt(), $details->updatedAt());

        $page = $queryBus->handle(new GetEntriesByGroup(
            EntryType::SYSTEM,
            null,
            'settings',
            new Pagination(1, 2),
        ));
        self::assertSame(2, $page->total());
        self::assertSame('second-id', $page->items()[0]->uuid());
        self::assertSame('second-key', $page->items()[0]->lookupKey());
        self::assertSame('second-value', $page->items()[0]->lookupValue());
        self::assertSame([], $page->items()[0]->details());
        self::assertTrue($page->items()[0]->isActive());
        self::assertGreaterThan(0, $page->items()[0]->createdAt()->getTimestamp());

        $commandBus->handle(new RemoveEntry(new EntryIdentity(
            EntryType::SYSTEM,
            null,
            'settings',
            'second-key',
        ), $accessControl));
        self::assertNull($queryBus->handle(new GetEntryIdByCriteria(
            EntryType::SYSTEM,
            null,
            'settings',
            'second-key',
        )));
        self::assertNull($queryBus->handle(new GetEntryByCriteria(
            EntryType::SYSTEM,
            null,
            'settings',
            'missing-key',
        )));
        $groupPage = $queryBus->handle(new GetEntryGroupsByType(EntryType::SYSTEM, null));
        self::assertSame(['settings'], $groupPage->items());
        self::assertSame(
            1,
            $queryBus->handle(new GetEntriesByGroup(
                EntryType::SYSTEM,
                null,
                'settings',
                new Pagination(10, 1),
            ))->total(),
        );

        $this->expectException(ResourceNotFound::class);
        $commandBus->handle(new RemoveEntry(new EntryIdentity(
            EntryType::SYSTEM,
            null,
            'settings',
            'second-key',
        ), $accessControl));
    }

    private function container(): Container
    {
        $container = new Container();
        $store     = new EntryStore();
        $container->set(EntryReadRepositoryContract::class, new EntryReadRepository($store));
        $container->set(EntryWriteRepositoryContract::class, new EntryWriteRepository($store));
        $container->set(DomainEventPublisher::class, $this->createStub(DomainEventPublisher::class));
        $transaction = $this->createStub(IntegrationEventTransaction::class);
        $transaction->method('execute')->willReturnCallback(
            static function (callable $work): void {
                $event = $work();
                self::assertInstanceOf(IntegrationEvent::class, $event);
            },
        );
        $container->set(IntegrationEventTransaction::class, $transaction);

        return $container;
    }
}
