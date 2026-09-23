<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\AddEntryHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddEntryHandlerTest extends TestCase
{
    #[Test]
    public function itRejectsTheCommandBeforeStartingTheTransaction(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(AddEntryHandler::REQUIRED_PRIVILEGE)
            ->willThrowException(ResourceAccessForbidden::create('Forbidden.'));
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::never())->method('execute');
        $handler = new AddEntryHandler(
            $this->createStub(EntryWriteRepository::class),
            $transaction,
            $this->createStub(DomainEventPublisher::class),
        );
        $command = new AddEntry(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            $accessControl,
        );

        $this->expectException(ResourceAccessForbidden::class);

        $handler->handle($command);
    }

    #[Test]
    public function itPublishesTheDomainEventInsideTheIntegrationTransaction(): void
    {
        $calls         = [];
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(AddEntryHandler::REQUIRED_PRIVILEGE)
            ->willReturn(true);
        $command    = new AddEntry(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            $accessControl,
        );
        $repository = $this->createMock(EntryWriteRepository::class);
        $repository->expects(self::once())
            ->method('add')
            ->with(self::callback(static fn (Entry $entry): bool => $entry->id() === 'example-id'))
            ->willReturnCallback(static function (Entry $_entry) use (&$calls): void {
                $calls[] = 'repository';
            });
        $publisher = $this->createMock(DomainEventPublisher::class);
        $publisher->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent $_event) use (&$calls): void {
                $calls[] = 'domain-event';
            });
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())
            ->method('execute')
            ->with(self::anything())
            ->willReturnCallback(static function (
                callable $transactionalWork,
            ) use (&$calls): void {
                $calls[] = 'transaction-start';
                $event   = $transactionalWork();
                self::assertInstanceOf(EntryAdded::class, $event);
                $calls[] = 'transaction-end';
            });

        $handler = new AddEntryHandler($repository, $transaction, $publisher);
        $handler->handle($command);

        self::assertSame(
            ['transaction-start', 'repository', 'domain-event', 'transaction-end'],
            $calls,
        );
    }
}
