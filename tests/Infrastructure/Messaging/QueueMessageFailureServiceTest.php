<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Messaging;

use Backendbase\Infrastructure\Messaging\QueueMessageFailureService;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Persistence\QueueMessageFailureStore;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

final class QueueMessageFailureServiceTest extends TestCase
{
    #[Test]
    public function itRetriesTransientFailuresBelowTheAttemptLimit(): void
    {
        $now   = new DateTimeImmutable('2026-09-24T10:00:00+00:00');
        $store = $this->createMock(QueueMessageFailureStore::class);
        $store->expects(self::once())->method('recordFailure')
            ->with('events', 'message-id', 'temporary', $now, false)->willReturn(4);
        $store->expects(self::never())->method('markDeadLettered');

        $outcome = new QueueMessageFailureService($store, new FrozenClock($now))
            ->transientFailure('events', 'message-id', 'temporary');

        self::assertSame(QueueMessageHandlingOutcome::RETRY, $outcome);
    }

    #[Test]
    public function itRejectsAndMarksTransientFailuresAtTheAttemptLimit(): void
    {
        $now   = new DateTimeImmutable('2026-09-24T10:00:00+00:00');
        $store = $this->createMock(QueueMessageFailureStore::class);
        $store->expects(self::once())->method('recordFailure')
            ->with('events', 'message-id', 'temporary', $now, false)->willReturn(5);
        $store->expects(self::once())
            ->method('markDeadLettered')
            ->with('events', 'message-id', $now);

        $outcome = new QueueMessageFailureService($store, new FrozenClock($now))
            ->transientFailure('events', 'message-id', 'temporary');

        self::assertSame(QueueMessageHandlingOutcome::REJECT, $outcome);
    }

    #[Test]
    public function itRejectsPermanentFailuresAndClearsSuccessfulMessages(): void
    {
        $now   = new DateTimeImmutable('2026-09-24T10:00:00+00:00');
        $store = $this->createMock(QueueMessageFailureStore::class);
        $store->expects(self::once())
            ->method('recordFailure')
            ->with(
                'events',
                'message-id',
                'invalid',
                $now,
                true,
            )
            ->willReturn(1);
        $store->expects(self::once())->method('clear')->with('events', 'message-id');
        $service = new QueueMessageFailureService($store, new FrozenClock($now));

        self::assertSame(
            QueueMessageHandlingOutcome::REJECT,
            $service->permanentFailure('events', 'message-id', 'invalid'),
        );
        $service->succeeded('events', 'message-id');
    }
}
