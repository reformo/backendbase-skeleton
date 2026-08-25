<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Queue\OutboxMessagePublisher;
use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Integrations\OutboxRelay;
use Backendbase\Shared\Integrations\Operation\OutboxRelayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Ramsey\Uuid\Uuid;

use function min;

final readonly class DoctrineOutboxRelay implements OutboxRelay
{
    private const int CLAIM_TTL_SECONDS = 60;
    private const int FAILED            = 2;
    private const int NO_MESSAGE        = 0;
    private const string OUTBOX_TABLE   = 'integration_event_outbox';
    private const int PUBLISHED         = 1;

    public function __construct(private Connection $connection, private OutboxMessagePublisher $publisher)
    {
    }

    public function relay(int $limit): OutboxRelayResult
    {
        $published = 0;
        $failed    = 0;
        for ($processed = 0; $processed < $limit; $processed++) {
            $status = $this->processNext();
            if ($status === self::NO_MESSAGE) {
                break;
            }

            $published += $status === self::PUBLISHED ? 1 : 0;
            $failed    += $status === self::FAILED ? 1 : 0;
        }

        return new OutboxRelayResult($published, $failed);
    }

    private function processNext(): int
    {
        $message = $this->claimNextMessage();
        if ($message === false) {
            return self::NO_MESSAGE;
        }

        $published = $this->publisher->publish(
            (string) $message['id'],
            (string) $message['event_name'],
            (string) $message['event_version'],
            (string) $message['payload'],
        );
        if ($published) {
            $this->connection->update(
                self::OUTBOX_TABLE,
                [
                    'published_at' => DateTimeImmutable::create()->format('Y-m-d H:i:s.u'),
                    'claim_token' => null,
                    'claim_until' => null,
                ],
                ['id' => $message['id'], 'claim_token' => $message['claim_token']],
            );

            return self::PUBLISHED;
        }

        $this->recordFailure(
            (string) $message['id'],
            (string) $message['claim_token'],
            (int) $message['attempts'],
        );

        return self::FAILED;
    }

    /** @return array<string, mixed>|false */
    private function claimNextMessage(): array|false
    {
        return $this->connection->transactional(static function (Connection $connection): array|false {
            $now   = DateTimeImmutable::create();
            $query = $connection->createQueryBuilder()
                ->select('id', 'event_name', 'event_version', 'payload', 'attempts')
                ->from(self::OUTBOX_TABLE)
                ->where('published_at IS NULL')
                ->andWhere('available_at <= :now')
                ->andWhere('(claim_until IS NULL OR claim_until <= :now)')
                ->setParameter('now', $now->format('Y-m-d H:i:s.u'))
                ->orderBy('created_at', 'ASC')
                ->addOrderBy('id', 'ASC')
                ->setMaxResults(1);

            if (! $connection->getDatabasePlatform() instanceof SQLitePlatform) {
                $query->forUpdate(ConflictResolutionMode::SKIP_LOCKED);
            }

            $message = $query->executeQuery()->fetchAssociative();
            if ($message === false) {
                return false;
            }

            $claimToken = Uuid::uuid7()->toString();
            $connection->update(
                self::OUTBOX_TABLE,
                [
                    'claim_token' => $claimToken,
                    'claim_until' => $now->modify('+' . self::CLAIM_TTL_SECONDS . ' seconds')
                        ->format('Y-m-d H:i:s.u'),
                ],
                ['id' => $message['id']],
            );
            $message['claim_token'] = $claimToken;

            return $message;
        });
    }

    private function recordFailure(
        string $messageId,
        string $claimToken,
        int $currentAttempts,
    ): void {
        $attempts = $currentAttempts + 1;
        $now      = DateTimeImmutable::create();
        $delay    = min(300, 2 ** min($attempts, 8));
        $this->connection->update(
            self::OUTBOX_TABLE,
            [
                'attempts' => $attempts,
                'available_at' => $now->modify('+' . $delay . ' seconds')->format('Y-m-d H:i:s.u'),
                'last_error' => 'publish-failed',
                'claim_token' => null,
                'claim_until' => null,
            ],
            ['id' => $messageId, 'claim_token' => $claimToken],
        );
    }
}
