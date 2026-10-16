<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\AddEntryHandler;
use Backendbase\Domain\ExampleCatalog\Application\EntryEventTransaction;
use Backendbase\Domain\ExampleCatalog\Application\EntryWrites;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

final class AddEntryHandlerTest extends TestCase
{
    #[Test]
    public function itPublishesTheDomainEventInsideTheIntegrationTransaction(): void
    {
        $now        = new DateTimeImmutable('2026-10-16T11:00:00.654321Z');
        $clock      = new FrozenClock(new DateTimeImmutable('2026-10-16T10:00:00.654321Z'));
        $calls      = [];
        $command    = new AddEntry(
            '018f0000-0000-7000-8000-000000000001',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
        );
        $repository = $this->createMock(EntryWriteRepository::class);
        $repository->expects(self::once())
            ->method('add')
            ->with(self::callback(static fn (Entry $entry): bool => $entry->id() === '018f0000-0000-7000-8000-000000000001'))
            ->willReturnCallback(static function (Entry $entry) use (&$calls, $now): void {
                $state = $entry->snapshot();
                self::assertEquals($now, $state->createdAt());
                self::assertEquals($now, $state->updatedAt());
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
            ) use (
                &$calls,
                $clock,
): void {
                $calls[] = 'transaction-start';
                $clock->advance(new DateInterval('PT1H'));
                $event = $transactionalWork();
                self::assertInstanceOf(EntryAdded::class, $event);
                $calls[] = 'transaction-end';
            });

        $handler = new AddEntryHandler(new EntryWrites($repository, $clock), new EntryEventTransaction($transaction, $publisher));
        $handler->handle($command);

        self::assertSame(
            ['transaction-start', 'repository', 'domain-event', 'transaction-end'],
            $calls,
        );
    }
}
