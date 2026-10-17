<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Outbox;

use Backendbase\Shared\Integrations\ExternalIntegrationEventRegistry;
use Backendbase\Shared\Integrations\Messaging\ReplayWindow;
use Backendbase\Shared\Integrations\OutboxReplay;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function is_string;

final readonly class DoctrineOutboxReplay implements OutboxReplay
{
    public function __construct(private Connection $connection, private Clock $clock, private ExternalIntegrationEventRegistry $registry)
    {
    }

    public function schedule(string $messageId, RecoveryEvidence $evidence, bool $apply = false): void
    {
        $clock      = $this->clock;
        $now        = $clock->now();
        $timestamp  = $now->format('Y-m-d H:i:s.u');
        $window     = new ReplayWindow($clock);
        $registry   = $this->registry;
        $connection = $this->connection;
        $connection->transactional(static function (Connection $connection) use ($messageId, $evidence, $apply, $timestamp, $window, $registry): void {
            $query = $connection->createQueryBuilder();
            $query->select('event_name', 'event_version', 'destination', 'occurred_at', 'published_at', 'claim_expires_at');
            $query->from('integration_event_outbox');
            $query->where('id = :id');
            $query->setParameter('id', $messageId);
            if (! $connection->getDatabasePlatform() instanceof SQLitePlatform) {
                $query->forUpdate();
            }

            $result = $query->executeQuery();
            $row    = $result->fetchAssociative();
            if ($row === false || $row['published_at'] === null || ! is_string($row['destination']) || $row['destination'] === '') {
                throw new RuntimeException('A published message with recorded consumer identity is required.');
            }

            $window->assertEligible((string) $row['occurred_at']);
            $registry->messageClass((string) $row['event_name'] . '_Event', (string) $row['event_version']);
            if ($row['claim_expires_at'] !== null && $row['claim_expires_at'] >= $timestamp) {
                throw new RuntimeException('An active publication claim cannot be replayed.');
            }

            $unresolved = $connection->fetchOne('SELECT 1 FROM integration_event_inbox WHERE message_id = :id AND consumer_name = :consumer AND processed_at IS NULL', ['id' => $messageId, 'consumer' => $row['destination']]);
            if ($unresolved !== false) {
                throw new RuntimeException('Resolve the unknown external outcome before replay.');
            }

            if (! $apply) {
                return;
            }

            $identifier = Uuid::uuid7();
            $connection->insert('integration_event_replay', [
                'id' => $identifier->getBytes(),
                'message_id' => $messageId,
                'consumer_name' => $row['destination'],
                'requested_at' => $timestamp,
                ...$evidence->auditFields(),
            ], ['id' => ParameterType::BINARY]);
            $connection->update('integration_event_outbox', [
                'published_at' => null,
                'available_at' => $timestamp,
                'claim_token' => null,
                'claim_expires_at' => null,
            ], ['id' => $messageId]);
        });
    }
}
