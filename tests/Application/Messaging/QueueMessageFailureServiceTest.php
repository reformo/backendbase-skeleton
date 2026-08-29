<?php

declare(strict_types=1);

namespace Tests\Application\Messaging;

use Backendbase\Application\Messaging\QueueMessageFailureService;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Persistence\QueueMessageFailureStore;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueMessageFailureServiceTest extends TestCase
{
    #[Test]
    public function itRetriesTransientFailuresBelowTheAttemptLimit(): void
    {
        $store = $this->createMock(QueueMessageFailureStore::class);
        $store->expects(self::once())->method('recordFailure')->willReturn(4);
        $store->expects(self::never())->method('markDeadLettered');

        $outcome = new QueueMessageFailureService($store)
            ->transientFailure('events', 'message-id', 'temporary');

        self::assertSame(QueueMessageHandlingOutcome::RETRY, $outcome);
    }

    #[Test]
    public function itRejectsAndMarksTransientFailuresAtTheAttemptLimit(): void
    {
        $store = $this->createMock(QueueMessageFailureStore::class);
        $store->expects(self::once())->method('recordFailure')->willReturn(5);
        $store->expects(self::once())
            ->method('markDeadLettered')
            ->with('events', 'message-id', self::isInstanceOf(DateTimeImmutable::class));

        $outcome = new QueueMessageFailureService($store)
            ->transientFailure('events', 'message-id', 'temporary');

        self::assertSame(QueueMessageHandlingOutcome::REJECT, $outcome);
    }

    #[Test]
    public function itRejectsPermanentFailuresAndClearsSuccessfulMessages(): void
    {
        $store = $this->createMock(QueueMessageFailureStore::class);
        $store->expects(self::once())
            ->method('recordFailure')
            ->with(
                'events',
                'message-id',
                'invalid',
                self::isInstanceOf(DateTimeImmutable::class),
                true,
            )
            ->willReturn(1);
        $store->expects(self::once())->method('clear')->with('events', 'message-id');
        $service = new QueueMessageFailureService($store);

        self::assertSame(
            QueueMessageHandlingOutcome::REJECT,
            $service->permanentFailure('events', 'message-id', 'invalid'),
        );
        $service->succeeded('events', 'message-id');
    }
}
