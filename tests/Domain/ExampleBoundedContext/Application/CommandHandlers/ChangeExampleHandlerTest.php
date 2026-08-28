<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Application\CommandHandlers;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\ChangeExampleHandler;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleChanged;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeExampleHandlerTest extends TestCase
{
    #[Test]
    public function itRejectsTheCommandBeforeStartingTheTransaction(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(ChangeExampleHandler::REQUIRED_PRIVILEGE)
            ->willThrowException(ResourceAccessForbidden::create('Forbidden.'));
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::never())->method('execute');
        $handler = new ChangeExampleHandler(
            $this->createStub(ExampleWriteRepository::class),
            $transaction,
        );
        $command = new ChangeExample(
            new ExampleIdentity(ExampleType::SYSTEM, null, 'settings', 'page-size'),
            $accessControl,
        );

        $this->expectException(ResourceAccessForbidden::class);

        $handler->handle($command);
    }

    #[Test]
    public function itResolvesTheWriteTargetInsideTheIntegrationTransaction(): void
    {
        $calls      = [];
        $identity   = new ExampleIdentity(ExampleType::SYSTEM, null, 'settings', 'page-size');
        $example    = Example::create(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $repository = $this->createMock(ExampleWriteRepository::class);
        $repository->expects(self::once())
            ->method('getActiveByIdentity')
            ->with($identity)
            ->willReturnCallback(static function (ExampleIdentity $_identity) use (&$calls, $example): Example {
                $calls[] = 'lookup';

                return $example;
            });
        $repository->expects(self::once())
            ->method('save')
            ->with($example)
            ->willReturnCallback(static function (Example $changedExample) use (&$calls): void {
                $state = $changedExample->snapshot();
                self::assertSame('50', $state['lookupValue']);
                $calls[] = 'save';
            });
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (callable $transactionalWork) use (&$calls): void {
                $calls[] = 'transaction-start';
                $event   = $transactionalWork();
                self::assertInstanceOf(ExampleChanged::class, $event);
                self::assertSame('example-id', $event->exampleId());
                $calls[] = 'transaction-end';
            });
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(ChangeExampleHandler::REQUIRED_PRIVILEGE)
            ->willReturn(true);
        $command = new ChangeExample($identity, $accessControl);
        $command->setValue('50');

        $handler = new ChangeExampleHandler($repository, $transaction);
        $handler->handle($command);

        self::assertSame(['transaction-start', 'lookup', 'save', 'transaction-end'], $calls);
    }
}
