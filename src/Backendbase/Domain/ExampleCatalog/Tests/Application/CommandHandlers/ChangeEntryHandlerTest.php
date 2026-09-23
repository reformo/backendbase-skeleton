<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\ChangeEntryHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryChanged;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeEntryHandlerTest extends TestCase
{
    #[Test]
    public function itRejectsTheCommandBeforeStartingTheTransaction(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(ChangeEntryHandler::REQUIRED_PRIVILEGE)
            ->willThrowException(ResourceAccessForbidden::create('Forbidden.'));
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::never())->method('execute');
        $handler = new ChangeEntryHandler(
            $this->createStub(EntryWriteRepository::class),
            $transaction,
        );
        $command = new ChangeEntry(
            new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'page-size'),
            $accessControl,
        );

        $this->expectException(ResourceAccessForbidden::class);

        $handler->handle($command);
    }

    #[Test]
    public function itResolvesTheWriteTargetInsideTheIntegrationTransaction(): void
    {
        $calls      = [];
        $identity   = new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'page-size');
        $entry      = Entry::create(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $repository = $this->createMock(EntryWriteRepository::class);
        $repository->expects(self::once())
            ->method('getActiveByIdentity')
            ->with($identity)
            ->willReturnCallback(static function (EntryIdentity $_identity) use (&$calls, $entry): Entry {
                $calls[] = 'lookup';

                return $entry;
            });
        $repository->expects(self::once())
            ->method('save')
            ->with($entry)
            ->willReturnCallback(static function (Entry $changedEntry) use (&$calls): void {
                $state = $changedEntry->snapshot();
                self::assertSame('50', $state->lookupValue());
                $calls[] = 'save';
            });
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (callable $transactionalWork) use (&$calls): void {
                $calls[] = 'transaction-start';
                $event   = $transactionalWork();
                self::assertInstanceOf(EntryChanged::class, $event);
                self::assertSame('example-id', $event->entryId());
                $calls[] = 'transaction-end';
            });
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(ChangeEntryHandler::REQUIRED_PRIVILEGE)
            ->willReturn(true);
        $command = new ChangeEntry($identity, $accessControl);
        $command->setValue('50');

        $handler = new ChangeEntryHandler($repository, $transaction);
        $handler->handle($command);

        self::assertSame(['transaction-start', 'lookup', 'save', 'transaction-end'], $calls);
    }
}
