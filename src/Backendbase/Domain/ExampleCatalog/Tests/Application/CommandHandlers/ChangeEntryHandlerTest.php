<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\ChangeEntryHandler;
use Backendbase\Domain\ExampleCatalog\Application\EntryEventTransaction;
use Backendbase\Domain\ExampleCatalog\Application\EntryWrites;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryChanged;
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

final class ChangeEntryHandlerTest extends TestCase
{
    #[Test]
    public function itResolvesTheWriteTargetInsideTheIntegrationTransaction(): void
    {
        $now        = new DateTimeImmutable('2026-10-16T11:00:00.654321Z');
        $clock      = new FrozenClock(new DateTimeImmutable('2026-10-16T10:00:00.654321Z'));
        $calls      = [];
        $identity   = new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'page-size');
        $entry      = Entry::create(
            '018f0000-0000-7000-8000-000000000001',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
            new DateTimeImmutable('2026-10-16T10:00:00.123456Z'),
        );
        $repository = $this->createMock(EntryWriteRepository::class);
        $repository->expects(self::once())
            ->method('getActiveByIdentity')
            ->with($identity)
            ->willReturnCallback(static function (EntryIdentity $_identity) use (&$calls, $entry, $clock): Entry {
                $calls[] = 'lookup';
                $clock->advance(new DateInterval('PT1H'));

                return $entry;
            });
        $repository->expects(self::once())
            ->method('save')
            ->with($entry)
            ->willReturnCallback(static function (Entry $changedEntry) use (&$calls, $now): void {
                $state = $changedEntry->snapshot();
                self::assertSame('50', $state->lookupValue());
                self::assertEquals($now, $state->updatedAt());
                $calls[] = 'save';
            });
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (callable $transactionalWork) use (&$calls, $now): void {
                $calls[] = 'transaction-start';
                $event   = $transactionalWork();
                self::assertInstanceOf(EntryChanged::class, $event);
                self::assertEquals($now, $event->occurredOn());
                self::assertSame('018f0000-0000-7000-8000-000000000001', $event->entryId());
                $calls[] = 'transaction-end';
            });
        $command = new ChangeEntry($identity);
        $command->setValue('50');

        $handler = new ChangeEntryHandler(new EntryWrites($repository, $clock), new EntryEventTransaction($transaction, $this->createStub(DomainEventPublisher::class)));
        $handler->handle($command);

        self::assertSame(['transaction-start', 'lookup', 'save', 'transaction-end'], $calls);
    }
}
