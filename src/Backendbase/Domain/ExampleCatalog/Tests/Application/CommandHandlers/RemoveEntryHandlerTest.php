<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\RemoveEntryHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveEntryHandlerTest extends TestCase
{
    #[Test]
    public function itRejectsTheCommandBeforeStartingTheTransaction(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(RemoveEntryHandler::REQUIRED_PRIVILEGE)
            ->willThrowException(ResourceAccessForbidden::create('Forbidden.'));
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::never())->method('execute');
        $handler = new RemoveEntryHandler(
            $this->createStub(EntryWriteRepository::class),
            $transaction,
        );
        $command = new RemoveEntry(
            new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'page-size'),
            $accessControl,
        );

        $this->expectException(ResourceAccessForbidden::class);

        $handler->handle($command);
    }
}
