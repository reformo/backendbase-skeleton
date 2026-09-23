<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineExternalEffectInbox;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox\ExternalEffectClaims;
use Backendbase\Shared\Persistence\ExternalEffectInProgress;
use Backendbase\Shared\Persistence\ExternalEffectOutcomeUnknown;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\Time\FrozenClock;

final class DoctrineExternalEffectInboxTest extends TestCase
{
    private Connection $connection;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock      = new FrozenClock(new DateTimeImmutable('2026-09-24T10:00:00+00:00'));
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_inbox ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id VARCHAR(36) NOT NULL, '
            . 'event_name VARCHAR(190) NOT NULL, '
            . 'received_at TEXT NOT NULL, '
            . 'processed_at TEXT DEFAULT NULL, '
            . 'claimed_until TEXT DEFAULT NULL, '
            . 'claim_token VARCHAR(36) DEFAULT NULL, '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ')',
        );
    }

    #[Test]
    public function itRunsTheExternalEffectOutsideTheClaimTransactionOnce(): void
    {
        $inbox  = new DoctrineExternalEffectInbox($this->connection, $this->clock);
        $calls  = 0;
        $effect = function () use (&$calls): void {
            self::assertFalse($this->connection->isTransactionActive());
            self::assertSame('2026-09-24 10:05:00.000000', $this->connection->fetchOne(
                "SELECT claimed_until FROM integration_event_inbox WHERE message_id = 'message-id'",
            ));
            $this->clock->advance(new DateInterval('PT2S'));
            ++$calls;
        };

        $inbox->processOnce('email', 'message-id', 'Notification_Email', $effect);
        $inbox->processOnce('email', 'message-id', 'Notification_Email', $effect);

        self::assertSame(1, $calls);
        self::assertSame('2026-09-24 10:00:02.000000', $this->processedAt());
    }

    #[Test]
    public function itDoesNotRetryAnExternalEffectAfterAFailure(): void
    {
        $inbox = new DoctrineExternalEffectInbox($this->connection, $this->clock);
        $calls = 0;
        try {
            $inbox->processOnce(
                'email',
                'message-id',
                'Notification_Email',
                static function () use (&$calls): void {
                    ++$calls;

                    throw new RuntimeException('Provider unavailable.');
                },
            );
            self::fail('The external failure must escape the inbox.');
        } catch (ExternalEffectOutcomeUnknown $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }

        try {
            $inbox->processOnce(
                'email',
                'message-id',
                'Notification_Email',
                static function () use (&$calls): void {
                    ++$calls;
                },
            );
            self::fail('An indeterminate external effect must not run again.');
        } catch (ExternalEffectOutcomeUnknown) {
        }

        self::assertSame(1, $calls);
        self::assertNull($this->processedAt());
    }

    #[Test]
    public function itDoesNotRepeatAnExpiredIncompleteAttempt(): void
    {
        $this->insertIncompleteAttempt('2026-09-24 09:59:59.999999');
        $inbox = new DoctrineExternalEffectInbox($this->connection, $this->clock);
        $calls = 0;

        $this->expectException(ExternalEffectOutcomeUnknown::class);
        try {
            $inbox->processOnce(
                'email',
                'message-id',
                'Notification_Email',
                static function () use (&$calls): void {
                    ++$calls;
                },
            );
        } finally {
            self::assertSame(0, $calls);
        }
    }

    #[Test]
    public function itReportsAnActiveAttemptWithoutRepeatingIt(): void
    {
        $this->insertIncompleteAttempt('2026-09-24 10:00:00.000000');
        $inbox = new DoctrineExternalEffectInbox($this->connection, $this->clock);
        $calls = 0;

        $this->expectException(ExternalEffectInProgress::class);
        try {
            $inbox->processOnce(
                'email',
                'message-id',
                'Notification_Email',
                static function () use (&$calls): void {
                    ++$calls;
                },
            );
        } finally {
            self::assertSame(0, $calls);
        }
    }

    #[Test]
    public function itReportsACompletedEffectWhoseClaimExpiresBeforeCompletion(): void
    {
        $inbox = new DoctrineExternalEffectInbox($this->connection, $this->clock);

        $this->expectException(ExternalEffectOutcomeUnknown::class);
        try {
            $inbox->processOnce(
                'email',
                'message-id',
                'Notification_Email',
                function (): void {
                    $this->connection->update(
                        'integration_event_inbox',
                        ['claim_token' => 'replacement-claim'],
                        ['message_id' => 'message-id'],
                    );
                },
            );
        } catch (ExternalEffectOutcomeUnknown $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());

            throw $exception;
        }
    }

    #[Test]
    public function itReportsAClaimThatCannotBeReadAfterAConflict(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $method = new ReflectionMethod(ExternalEffectClaims::class, 'existingClaim');

        $this->expectException(RuntimeException::class);

        $method->invoke(null, $connection, 'email', 'message-id', '2026-08-25 10:00:00.000000');
    }

    private function processedAt(): mixed
    {
        return $this->connection->fetchOne(
            "SELECT processed_at FROM integration_event_inbox WHERE message_id = 'message-id'",
        );
    }

    private function insertIncompleteAttempt(string $claimedUntil): void
    {
        $this->connection->insert('integration_event_inbox', [
            'consumer_name' => 'email',
            'message_id' => 'message-id',
            'event_name' => 'Notification_Email',
            'received_at' => '2000-01-01 00:00:00.000000',
            'processed_at' => null,
            'claimed_until' => $claimedUntil,
            'claim_token' => 'claim-token',
        ]);
    }
}
