<?php

declare(strict_types=1);

namespace Tests\Support\Persistence;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Tests\Support\Time\FrozenClock;

final readonly class MessageRecoveryFixture
{
    public function __construct(private Connection $connection, private FrozenClock $clock)
    {
    }

    public static function create(): self
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE integration_event_outbox (id TEXT PRIMARY KEY, event_name TEXT, event_version TEXT, destination TEXT, occurred_at TEXT, published_at TEXT, claim_expires_at TEXT, claim_token TEXT, available_at TEXT, payload TEXT, attempts INTEGER)');
        $connection->executeStatement('CREATE TABLE integration_event_inbox (consumer_name TEXT, message_id TEXT, event_name TEXT, processed_at TEXT, claim_expires_at TEXT, claim_token TEXT, PRIMARY KEY(consumer_name, message_id))');
        $connection->executeStatement('CREATE TABLE integration_event_replay (id BLOB PRIMARY KEY, message_id TEXT, consumer_name TEXT, requested_at TEXT, operator_name TEXT, evidence_reference TEXT)');
        $connection->executeStatement('CREATE TABLE external_effect_resolution (id BLOB PRIMARY KEY, message_id TEXT, consumer_name TEXT, decision TEXT, resolved_at TEXT, operator_name TEXT, evidence_reference TEXT)');
        $connection->executeStatement('CREATE TABLE integration_event_delivery_failure (consumer_name TEXT, message_id TEXT, dead_lettered_at TEXT, PRIMARY KEY(consumer_name, message_id))');

        return new self($connection, new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z')));
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function clock(): FrozenClock
    {
        return $this->clock;
    }

    /** @param array<string, mixed> $changes */
    public function outbox(array $changes = []): void
    {
        $connection = $this->connection;
        $row        = [
            'id' => 'original-message',
            'event_name' => 'Example_NewExampleAdded',
            'event_version' => '1.0',
            'destination' => 'events',
            'occurred_at' => '2026-10-13 10:00:00.000000',
            'published_at' => '2026-10-13 10:01:00.000000',
            'claim_expires_at' => null,
            'claim_token' => null,
            'available_at' => '2026-10-13 10:00:00.000000',
            'payload' => '{"original":true}',
            'attempts' => 2,
        ];
        $connection->insert('integration_event_outbox', [...$row, ...$changes]);
    }

    /** @param array<string, mixed> $changes */
    public function inbox(array $changes = []): void
    {
        $connection = $this->connection;
        $row        = [
            'consumer_name' => 'events',
            'message_id' => 'original-message',
            'event_name' => 'Example_NewExampleAdded',
            'processed_at' => null,
            'claim_expires_at' => '2026-10-13 10:05:00.000000',
            'claim_token' => 'old-claim',
        ];
        $connection->insert('integration_event_inbox', [...$row, ...$changes]);
    }
}
