<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox;

use Backendbase\Shared\Persistence\ExternalEffectResolution;
use Backendbase\Shared\Persistence\ExternalEffectResolver;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class DoctrineExternalEffectResolver implements ExternalEffectResolver
{
    public function __construct(private Connection $connection, private Clock $clock)
    {
    }

    public function resolve(
        string $consumerName,
        string $messageId,
        ExternalEffectResolution $resolution,
        RecoveryEvidence $evidence,
        bool $apply = false,
    ): void {
        $clock      = $this->clock;
        $now        = $clock->now();
        $timestamp  = $now->format('Y-m-d H:i:s.u');
        $connection = $this->connection;
        $connection->transactional(static function (Connection $connection) use ($consumerName, $messageId, $resolution, $evidence, $apply, $timestamp): void {
            $query = $connection->createQueryBuilder();
            $query->select('event_name', 'processed_at', 'claim_expires_at');
            $query->from('integration_event_inbox');
            $query->where('consumer_name = :consumer AND message_id = :message');
            $query->setParameters(['consumer' => $consumerName, 'message' => $messageId]);
            if (! $connection->getDatabasePlatform() instanceof SQLitePlatform) {
                $query->forUpdate();
            }

            $result = $query->executeQuery();
            $row    = $result->fetchAssociative();
            self::assertUncertain($row, $timestamp);
            if (! $apply) {
                return;
            }

            $identifier = Uuid::uuid7();
            $connection->insert('external_effect_resolution', [
                'id' => $identifier->getBytes(),
                'consumer_name' => $consumerName,
                'message_id' => $messageId,
                'decision' => $resolution->value,
                'resolved_at' => $timestamp,
                ...$evidence->auditFields(),
            ], ['id' => ParameterType::BINARY]);
            $identity = ['consumer_name' => $consumerName, 'message_id' => $messageId];
            if ($resolution === ExternalEffectResolution::ACCEPTED) {
                $connection->update('integration_event_inbox', [
                    'processed_at' => $timestamp,
                    'claim_expires_at' => null,
                    'claim_token' => null,
                ], $identity);

                return;
            }

            $connection->delete('integration_event_inbox', $identity);
        });
    }

    /** @param array<string, mixed>|false $row */
    private static function assertUncertain(array|false $row, string $now): void
    {
        if ($row === false || $row['processed_at'] !== null) {
            throw new RuntimeException('An unresolved external-effect record is required.');
        }

        if ($row['claim_expires_at'] !== null && $row['claim_expires_at'] >= $now) {
            throw new RuntimeException('An active external-effect claim cannot be resolved.');
        }
    }
}
