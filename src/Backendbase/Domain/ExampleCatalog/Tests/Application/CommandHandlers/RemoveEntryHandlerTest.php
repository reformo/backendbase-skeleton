<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\RemoveEntryHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveEntryHandlerTest extends TestCase
{
    #[Test]
    public function itRemovesTheWriteTargetInsideTheIntegrationTransaction(): void
    {
        $calls      = [];
        $identity   = new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'page-size');
        $entry      = Entry::create('example-id', EntryType::SYSTEM, null, 'settings', true, 'page-size', '25', []);
        $repository = $this->createMock(EntryWriteRepository::class);
        $repository->expects(self::once())->method('getActiveByIdentity')->with($identity)
            ->willReturnCallback(static function (EntryIdentity $_identity) use (&$calls, $entry): Entry {
                $calls[] = 'lookup';

                return $entry;
            });
        $repository->expects(self::once())->method('save')->with($entry)
            ->willReturnCallback(static function (Entry $removedEntry) use (&$calls): void {
                $snapshot = $removedEntry->snapshot();
                self::assertNotNull($snapshot->removedAt());
                $calls[] = 'save';
            });
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())->method('execute')
            ->willReturnCallback(static function (callable $work) use (&$calls): void {
                $calls[] = 'transaction-start';
                $event   = $work();
                self::assertInstanceOf(EntryRemoved::class, $event);
                self::assertSame('example-id', $event->entryId());
                $calls[] = 'transaction-end';
            });
        $handler = new RemoveEntryHandler($repository, $transaction);

        $handler->handle(new RemoveEntry($identity));

        self::assertSame(['transaction-start', 'lookup', 'save', 'transaction-end'], $calls);
    }
}
