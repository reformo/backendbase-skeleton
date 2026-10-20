<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\RemoveEntryHandler;
use Backendbase\Domain\ExampleCatalog\Application\EntryEventTransaction;
use Backendbase\Domain\ExampleCatalog\Application\EntryWrites;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

final class RemoveEntryHandlerTest extends TestCase
{
    #[Test]
    public function itRemovesTheWriteTargetInsideTheIntegrationTransaction(): void
    {
        $now        = new DateTimeImmutable('2026-10-16T11:00:00.654321Z');
        $clock      = new FrozenClock(new DateTimeImmutable('2026-10-16T10:00:00.654321Z'));
        $calls      = [];
        $identity   = new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'page-size');
        $entry      = Entry::create('018f0000-0000-7000-8000-000000000001', EntryType::SYSTEM, null, 'settings', true, 'page-size', '25', [], new DateTimeImmutable('2026-10-16T10:00:00.123456Z'));
        $repository = $this->createMock(EntryWriteRepository::class);
        $repository->expects(self::once())->method('getActiveByIdentity')->with($identity)
            ->willReturnCallback(static function (EntryIdentity $_identity) use (&$calls, $entry, $clock): Entry {
                $calls[] = 'lookup';
                $clock->advance(new DateInterval('PT1H'));

                return $entry;
            });
        $repository->expects(self::once())->method('save')->with($entry)
            ->willReturnCallback(static function (Entry $removedEntry) use (&$calls, $now): void {
                $snapshot = $removedEntry->snapshot();
                self::assertEquals($now, $snapshot->removedAt());
                self::assertEquals($now, $snapshot->updatedAt());
                $calls[] = 'save';
            });
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())->method('execute')
            ->willReturnCallback(static function (callable $work) use (&$calls, $now): void {
                $calls[] = 'transaction-start';
                $event   = $work();
                self::assertInstanceOf(EntryRemoved::class, $event);
                self::assertEquals($now, $event->occurredOn());
                self::assertSame('018f0000-0000-7000-8000-000000000001', $event->entryId());
                $calls[] = 'transaction-end';
            });
        $handler = new RemoveEntryHandler(new EntryWrites($repository, $clock), new EntryEventTransaction($transaction, $this->createStub(DomainEventPublisher::class)));

        $handler->handle(new RemoveEntry($identity));

        self::assertSame(['transaction-start', 'lookup', 'save', 'transaction-end'], $calls);
    }
}
