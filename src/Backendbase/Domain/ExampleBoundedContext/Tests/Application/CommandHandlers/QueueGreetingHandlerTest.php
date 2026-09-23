<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\QueueGreetingHandler;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\QueueGreeting;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\GreetingRequested;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class QueueGreetingHandlerTest extends TestCase
{
    #[Test]
    public function itStoresTheVersionedGreetingInTheOutboxTransaction(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())->method('isAllowed')
            ->with(QueueGreetingHandler::REQUIRED_PRIVILEGE)->willReturn(true);
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())->method('execute')
            ->willReturnCallback(static function (callable $work): void {
                $event = $work();
                self::assertInstanceOf(GreetingRequested::class, $event);
                self::assertSame('Example_GreetingRequested', $event->eventName());
                self::assertSame('1.0', $event->eventVersion());
                self::assertSame(['fullname' => 'Ada Lovelace'], $event->getEventArguments());
            });

        $command = new QueueGreeting('Ada Lovelace', $accessControl);
        self::assertSame(['fullname' => 'Ada Lovelace'], $command->toArray());
        self::assertSame('{"fullname":"Ada Lovelace"}', json_encode($command, JSON_THROW_ON_ERROR));

        (new QueueGreetingHandler($transaction))->handle($command);
    }

    #[Test]
    public function itRejectsAnUnauthorizedGreetingBeforeTheOutboxWrite(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())->method('isAllowed')
            ->willThrowException(ResourceAccessForbidden::create('Forbidden.'));
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::never())->method('execute');

        $this->expectException(ResourceAccessForbidden::class);

        (new QueueGreetingHandler($transaction))->handle(new QueueGreeting('Ada Lovelace', $accessControl));
    }
}
