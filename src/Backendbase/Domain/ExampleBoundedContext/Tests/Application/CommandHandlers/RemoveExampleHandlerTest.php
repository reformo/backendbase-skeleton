<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\RemoveExampleHandler;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\RemoveExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveExampleHandlerTest extends TestCase
{
    #[Test]
    public function itRejectsTheCommandBeforeStartingTheTransaction(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(RemoveExampleHandler::REQUIRED_PRIVILEGE)
            ->willThrowException(ResourceAccessForbidden::create('Forbidden.'));
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::never())->method('execute');
        $handler = new RemoveExampleHandler(
            $this->createStub(ExampleWriteRepository::class),
            $transaction,
        );
        $command = new RemoveExample(
            new ExampleIdentity(ExampleType::SYSTEM, null, 'settings', 'page-size'),
            $accessControl,
        );

        $this->expectException(ResourceAccessForbidden::class);

        $handler->handle($command);
    }
}
