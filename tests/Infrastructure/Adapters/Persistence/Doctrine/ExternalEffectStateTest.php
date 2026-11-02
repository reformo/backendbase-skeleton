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
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

final class ExternalEffectStateTest extends TestCase
{
    private Connection $connection;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE integration_event_inbox (consumer_name TEXT, message_id TEXT, event_name TEXT, received_at TEXT, processed_at TEXT, claim_expires_at TEXT, claim_token TEXT, PRIMARY KEY (consumer_name, message_id))');
        $this->connection = $connection;
        $this->clock      = new FrozenClock(new DateTimeImmutable('2026-10-14 10:00:00 UTC'));
    }

    public function testInterruptedClaimIsActiveAtDeadlineThenUnknownUntilTheOriginalWorkerFinishes(): void
    {
        $connection = $this->connection;
        $clock      = $this->clock;
        $claims     = new ExternalEffectClaims($connection);
        $now        = $clock->now();
        $token      = $claims->claim('email', 'message-id', 'Notification_Email', $now);
        self::assertIsString($token);
        $clock->advance(new DateInterval('PT300S'));
        $inbox = new DoctrineExternalEffectInbox($connection, $clock);
        $this->assertBlocked($inbox, ExternalEffectInProgress::class);
        $clock->advance(new DateInterval('PT1S'));
        $this->assertBlocked($inbox, ExternalEffectOutcomeUnknown::class);
        $later = $clock->now();
        $claims->complete('email', 'message-id', $token, $later);
        $calls = 0;
        $inbox->processOnce('email', 'message-id', 'Notification_Email', static function () use (&$calls): void {
            $calls++;
        });
        self::assertSame(0, $calls);
    }

    public function testFailureToRecordACompletedEffectBlocksAutomaticRetry(): void
    {
        $connection = $this->connection;
        $clock      = $this->clock;
        $connection->executeStatement("CREATE TRIGGER reject_completion BEFORE UPDATE OF processed_at ON integration_event_inbox WHEN NEW.processed_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'synthetic completion failure'); END");
        $inbox = new DoctrineExternalEffectInbox($connection, $clock);
        $calls = 0;
        try {
            $inbox->processOnce('email', 'message-id', 'Notification_Email', static function () use (&$calls): void {
                $calls++;
            });
            self::fail('A completion storage failure must leave the outcome unknown.');
        } catch (ExternalEffectOutcomeUnknown $exception) {
            self::assertNotNull($exception->getPrevious());
        }

        $clock->advance(new DateInterval('PT301S'));
        $this->assertBlocked($inbox, ExternalEffectOutcomeUnknown::class);
        self::assertSame(1, $calls);
        self::assertNull($connection->fetchOne('SELECT processed_at FROM integration_event_inbox'));
    }

    public function testAnOuterTransactionCannotStartAnExternalEffect(): void
    {
        $connection = $this->connection;
        $inbox      = new DoctrineExternalEffectInbox($connection, $this->clock);
        $calls      = 0;
        $connection->beginTransaction();
        try {
            $inbox->processOnce('email', 'message-id', 'Notification_Email', static function () use (&$calls): void {
                $calls++;
            });
            self::fail('The provider must not run inside an outer transaction.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('commit', $exception->getMessage());
        } finally {
            $connection->rollBack();
        }

        self::assertSame(0, $calls);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM integration_event_inbox'));
    }

    /** @param class-string<ExternalEffectInProgress|ExternalEffectOutcomeUnknown> $expected */
    private function assertBlocked(DoctrineExternalEffectInbox $inbox, string $expected): void
    {
        try {
            $inbox->processOnce('email', 'message-id', 'Notification_Email', static function (): never {
                self::fail('A blocked external effect must not run.');
            });
            self::fail('The claim must remain blocked.');
        } catch (ExternalEffectInProgress | ExternalEffectOutcomeUnknown $exception) {
            self::assertInstanceOf($expected, $exception);
        }
    }
}
