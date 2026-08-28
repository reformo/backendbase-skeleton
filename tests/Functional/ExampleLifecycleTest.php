<?php

declare(strict_types=1);

namespace Tests\Functional;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleStore;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\RemoveExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ExampleReadRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Shared\CQRS\ContainerAwareCommandBus;
use Backendbase\Shared\CQRS\ContainerAwareQueryBus;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Primitives\Pagination;
use DI\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExampleLifecycleTest extends TestCase
{
    #[Test]
    public function itRunsTheCompleteExampleLifecycleThroughTheBuses(): void
    {
        $container     = $this->container();
        $commandBus    = new ContainerAwareCommandBus($container);
        $queryBus      = new ContainerAwareQueryBus($container);
        $accessControl = new Acl(['full-privileges']);

        $commandBus->handle(new AddNewExample(
            'first-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'first-key',
            'first-value',
            $accessControl,
            ['unit' => 'items'],
        ));
        $commandBus->handle(new AddNewExample(
            'second-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'second-key',
            'second-value',
            $accessControl,
        ));
        $commandBus->handle(new AddNewExample(
            'user-id',
            ExampleType::USER,
            42,
            'preferences',
            true,
            'theme',
            'dark',
            $accessControl,
        ));

        self::assertSame(
            ['settings'],
            $queryBus->handle(new GetExampleGroupsByType(ExampleType::SYSTEM, null)),
        );
        self::assertSame(
            'first-id',
            $queryBus->handle(new GetExampleIdByCriteria(
                ExampleType::SYSTEM,
                null,
                'settings',
                'first-key',
            )),
        );

        $change = new ChangeExample(new ExampleIdentity(
            ExampleType::SYSTEM,
            null,
            'settings',
            'first-key',
        ), $accessControl);
        $change->setIsActive(false)->setValue('changed')->setDetails(['unit' => 'rows']);
        $commandBus->handle($change);

        $details = $queryBus->handle(new GetExampleByCriteria(
            ExampleType::SYSTEM,
            null,
            'settings',
            'first-key',
        ));
        self::assertNotNull($details);
        self::assertSame('first-id', $details->uuid());
        self::assertSame(ExampleType::SYSTEM, $details->type());
        self::assertNull($details->typeTargetId());
        self::assertSame('settings', $details->group());
        self::assertSame('first-key', $details->lookupKey());
        self::assertSame('changed', $details->lookupValue());
        self::assertSame(['unit' => 'rows'], $details->details());
        self::assertFalse($details->isActive());
        self::assertGreaterThanOrEqual($details->createdAt(), $details->updatedAt());

        $page = $queryBus->handle(new GetExamplesByGroup(
            ExampleType::SYSTEM,
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

        $commandBus->handle(new RemoveExample(new ExampleIdentity(
            ExampleType::SYSTEM,
            null,
            'settings',
            'second-key',
        ), $accessControl));
        self::assertNull($queryBus->handle(new GetExampleIdByCriteria(
            ExampleType::SYSTEM,
            null,
            'settings',
            'second-key',
        )));
        self::assertNull($queryBus->handle(new GetExampleByCriteria(
            ExampleType::SYSTEM,
            null,
            'settings',
            'missing-key',
        )));
        self::assertSame(
            ['settings'],
            $queryBus->handle(new GetExampleGroupsByType(ExampleType::SYSTEM, null)),
        );
        self::assertSame(
            1,
            $queryBus->handle(new GetExamplesByGroup(
                ExampleType::SYSTEM,
                null,
                'settings',
                new Pagination(10, 1),
            ))->total(),
        );

        $this->expectException(ResourceNotFound::class);
        $commandBus->handle(new RemoveExample(new ExampleIdentity(
            ExampleType::SYSTEM,
            null,
            'settings',
            'second-key',
        ), $accessControl));
    }

    private function container(): Container
    {
        $container = new Container();
        $store     = new ExampleStore();
        $container->set(ExampleReadRepositoryContract::class, new ExampleReadRepository($store));
        $container->set(ExampleWriteRepositoryContract::class, new ExampleWriteRepository($store));
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
