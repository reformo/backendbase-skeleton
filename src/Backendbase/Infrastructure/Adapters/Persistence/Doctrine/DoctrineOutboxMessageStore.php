<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use Backendbase\Shared\Persistence\OutboxMessageStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Ramsey\Uuid\Uuid;

final readonly class DoctrineOutboxMessageStore implements OutboxMessageStore
{
    private const string OUTBOX_TABLE = 'integration_event_outbox';

    public function __construct(private Connection $connection)
    {
    }

    public function claimNext(DateTimeImmutable $availableAt, DateTimeImmutable $claimUntil): ClaimedOutboxMessage|null
    {
        return $this->connection->transactional(
            fn (Connection $connection): ClaimedOutboxMessage|null => $this->claim(
                $connection,
                $availableAt,
                $claimUntil,
            ),
        );
    }

    public function markPublished(ClaimedOutboxMessage $message, DateTimeImmutable $publishedAt): void
    {
        $this->connection->update(
            self::OUTBOX_TABLE,
            [
                'published_at' => $publishedAt->format('Y-m-d H:i:s.u'),
                'claim_token' => null,
                'claim_until' => null,
            ],
            ['id' => $message->id(), 'claim_token' => $message->claimToken()],
        );
    }

    public function recordPublicationFailure(
        ClaimedOutboxMessage $message,
        int $attempts,
        DateTimeImmutable $availableAt,
        string $failureType,
    ): void {
        $this->connection->update(
            self::OUTBOX_TABLE,
            [
                'attempts' => $attempts,
                'available_at' => $availableAt->format('Y-m-d H:i:s.u'),
                'last_error' => $failureType,
                'claim_token' => null,
                'claim_until' => null,
            ],
            ['id' => $message->id(), 'claim_token' => $message->claimToken()],
        );
    }

    private function claim(
        Connection $connection,
        DateTimeImmutable $availableAt,
        DateTimeImmutable $claimUntil,
    ): ClaimedOutboxMessage|null {
        $query = $connection->createQueryBuilder()
            ->select('id', 'event_name', 'event_version', 'payload', 'attempts')
            ->from(self::OUTBOX_TABLE)
            ->where('published_at IS NULL')
            ->andWhere('available_at <= :now')
            ->andWhere('(claim_until IS NULL OR claim_until <= :now)')
            ->setParameter('now', $availableAt->format('Y-m-d H:i:s.u'))
            ->orderBy('created_at', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setMaxResults(1);
        if (! $connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $query->forUpdate(ConflictResolutionMode::SKIP_LOCKED);
        }

        $message = $query->executeQuery()->fetchAssociative();
        if ($message === false) {
            return null;
        }

        $claimToken = Uuid::uuid7()->toString();
        $connection->update(
            self::OUTBOX_TABLE,
            [
                'claim_token' => $claimToken,
                'claim_until' => $claimUntil->format('Y-m-d H:i:s.u'),
            ],
            ['id' => $message['id']],
        );

        return new ClaimedOutboxMessage(
            (string) $message['id'],
            (string) $message['event_name'],
            (string) $message['event_version'],
            (string) $message['payload'],
            (int) $message['attempts'],
            $claimToken,
        );
    }
}
