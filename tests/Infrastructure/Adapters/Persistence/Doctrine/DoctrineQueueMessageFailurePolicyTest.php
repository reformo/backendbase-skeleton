<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineQueueMessageFailurePolicy;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoctrineQueueMessageFailurePolicyTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_delivery_failure ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id VARCHAR(36) NOT NULL, '
            . 'attempts INTEGER NOT NULL, '
            . 'last_failure_type VARCHAR(190) NOT NULL, '
            . 'last_failed_at TEXT NOT NULL, '
            . 'dead_lettered_at TEXT DEFAULT NULL, '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ')',
        );
    }

    #[Test]
    public function itRejectsATransientFailureAfterFiveAttempts(): void
    {
        $policy = new DoctrineQueueMessageFailurePolicy($this->connection);

        for ($attempt = 1; $attempt < 5; $attempt++) {
            self::assertSame(
                QueueMessageHandlingOutcome::RETRY,
                $policy->transientFailure('events', 'message-id', 'temporary'),
            );
        }

        self::assertSame(
            QueueMessageHandlingOutcome::REJECT,
            $policy->transientFailure('events', 'message-id', 'temporary'),
        );
        self::assertSame(5, (int) $this->connection->fetchOne(
            "SELECT attempts FROM integration_event_delivery_failure WHERE message_id = 'message-id'",
        ));
        self::assertNotNull($this->connection->fetchOne(
            "SELECT dead_lettered_at FROM integration_event_delivery_failure WHERE message_id = 'message-id'",
        ));
    }

    #[Test]
    public function itRejectsPermanentFailuresImmediatelyAndClearsSuccessfulMessages(): void
    {
        $policy = new DoctrineQueueMessageFailurePolicy($this->connection);

        self::assertSame(
            QueueMessageHandlingOutcome::REJECT,
            $policy->permanentFailure('events', 'message-id', 'invalid-payload'),
        );
        $policy->succeeded('events', 'message-id');

        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM integration_event_delivery_failure',
        ));
    }
}
