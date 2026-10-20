<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Application\CommandHandlers;

use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\QueueGreetingHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\QueueGreeting;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\GreetingRequested;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class QueueGreetingHandlerTest extends TestCase
{
    #[Test]
    public function itStoresTheVersionedGreetingInTheOutboxTransaction(): void
    {
        $clock         = new FrozenClock(new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())->method('isAllowed')
            ->with(QueueGreetingHandler::REQUIRED_PRIVILEGE)->willReturn(true);
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())->method('execute')
            ->willReturnCallback(static function (callable $work) use ($clock): void {
                $clock->advance(new DateInterval('PT1H'));
                $event = $work();
                self::assertInstanceOf(GreetingRequested::class, $event);
                self::assertSame($clock->now(), $event->occurredOn());
                self::assertSame('Example_GreetingRequested', $event->eventName());
                self::assertSame('1.0', $event->eventVersion());
                self::assertSame(['fullname' => 'Ada Lovelace'], $event->getEventArguments());
            });

        $command = new QueueGreeting('Ada Lovelace', $accessControl);
        self::assertSame(['fullname' => 'Ada Lovelace'], $command->toArray());
        self::assertSame('{"fullname":"Ada Lovelace"}', json_encode($command, JSON_THROW_ON_ERROR));

        (new QueueGreetingHandler($transaction, $clock))->handle($command);
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

        (new QueueGreetingHandler($transaction, new FrozenClock(new DateTimeImmutable('2026-10-19T10:00:00.123456Z'))))->handle(new QueueGreeting('Ada Lovelace', $accessControl));
    }
}
