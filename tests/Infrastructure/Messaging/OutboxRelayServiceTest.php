<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Messaging;

use Backendbase\Infrastructure\Messaging\OutboxRelayService;
use Backendbase\Infrastructure\Messaging\OutboxRetryPolicy;
use Backendbase\Shared\Integrations\Operation\OutboxPublicationResult;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use Backendbase\Shared\Persistence\OutboxMessageStore;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutboxRelayServiceTest extends TestCase
{
    #[Test]
    public function itOrchestratesPublicationAndPersistenceOutcomes(): void
    {
        $claimedAt  = new DateTimeImmutable('2026-09-24T10:00:00+00:00');
        $finishedAt = $claimedAt->modify('+2 seconds');
        $clock      = $this->createMock(Clock::class);
        $clock->expects(self::exactly(5))->method('now')->willReturnOnConsecutiveCalls(
            $claimedAt,
            $finishedAt,
            $claimedAt,
            $finishedAt,
            $claimedAt,
        );
        $publishedMessage = $this->message('published-id', 0);
        $failedMessage    = $this->message('failed-id', 2);
        $messageStore     = $this->createMock(OutboxMessageStore::class);
        $messageStore->expects(self::exactly(3))
            ->method('claimNext')
            ->with($claimedAt, $claimedAt->modify('+60 seconds'))
            ->willReturnOnConsecutiveCalls($publishedMessage, $failedMessage, null);
        $messageStore->expects(self::once())
            ->method('markPublished')
            ->with($publishedMessage, $finishedAt);
        $messageStore->expects(self::once())
            ->method('recordPublicationFailure')
            ->with(
                $failedMessage,
                3,
                $finishedAt->modify('+8 seconds'),
                'publish-failed',
            );
        $publisher = $this->createMock(OutboxPublisher::class);
        $publisher->expects(self::exactly(2))
            ->method('publish')
            ->willReturnOnConsecutiveCalls(
                OutboxPublicationResult::succeeded(),
                OutboxPublicationResult::failed(),
            );
        $relay = new OutboxRelayService($messageStore, $publisher, $clock);

        $result = $relay->relay(10);

        self::assertSame(1, $result->published());
        self::assertSame(1, $result->failed());
    }

    #[Test]
    public function itPreservesClaimAndBackoffDecisions(): void
    {
        $now = new DateTimeImmutable('2000-01-01 00:00:00 UTC');

        self::assertSame(
            '2000-01-01 00:01:00',
            OutboxRetryPolicy::claimUntil($now)->format('Y-m-d H:i:s'),
        );
        self::assertSame(
            '2000-01-01 00:00:02',
            OutboxRetryPolicy::nextAvailableAt($now, 1)->format('Y-m-d H:i:s'),
        );
        self::assertSame(
            '2000-01-01 00:04:16',
            OutboxRetryPolicy::nextAvailableAt($now, 20)->format('Y-m-d H:i:s'),
        );
    }

    private function message(string $id, int $attempts): ClaimedOutboxMessage
    {
        return new ClaimedOutboxMessage(
            $id,
            'Example_Removed',
            '1.0',
            '{"exampleId":"example-id"}',
            $attempts,
            $id . '-claim',
        );
    }
}
