<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox;

use Backendbase\Shared\Persistence\ExternalEffectInProgress;
use Backendbase\Shared\Persistence\ExternalEffectOutcomeUnknown;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function is_string;

final readonly class ExternalEffectClaims
{
    private const string INBOX_TABLE = 'integration_event_inbox';

    public function __construct(private Connection $connection, private int $claimTtlSeconds = 300)
    {
    }

    public function claim(
        string $consumerName,
        string $messageId,
        string $effectName,
        DateTimeImmutable $now,
    ): string|null {
        $identifier      = Uuid::uuid7();
        $claimToken      = $identifier->toString();
        $claimTtlSeconds = $this->claimTtlSeconds;
        $claimedUntil    = $now->modify('+' . $claimTtlSeconds . ' seconds');

        $receivedAtValue   = $now->format('Y-m-d H:i:s.u');
        $claimedUntilValue = $claimedUntil->format('Y-m-d H:i:s.u');

        $connection = $this->connection;

        return $connection->transactional(static function (Connection $connection) use (
            $consumerName,
            $messageId,
            $effectName,
            $receivedAtValue,
            $claimToken,
            $claimedUntilValue,
        ): string|null {
            try {
                $connection->insert(self::INBOX_TABLE, [
                    'consumer_name' => $consumerName,
                    'message_id' => $messageId,
                    'event_name' => $effectName,
                    'received_at' => $receivedAtValue,
                    'processed_at' => null,
                    'claimed_until' => $claimedUntilValue,
                    'claim_token' => $claimToken,
                ]);

                return $claimToken;
            } catch (UniqueConstraintViolationException) {
                return self::existingClaim(
                    $connection,
                    $consumerName,
                    $messageId,
                    $receivedAtValue,
                );
            }
        });
    }

    private static function existingClaim(
        Connection $connection,
        string $consumerName,
        string $messageId,
        string $now,
    ): null {
        $claim = $connection->fetchAssociative(
            'SELECT processed_at, claimed_until FROM ' . self::INBOX_TABLE
            . ' WHERE consumer_name = :consumerName AND message_id = :messageId',
            ['consumerName' => $consumerName, 'messageId' => $messageId],
        );
        if ($claim === false) {
            throw new RuntimeException('The external-effect inbox claim could not be read.');
        }

        if ($claim['processed_at'] !== null) {
            return null;
        }

        $claimedUntil = $claim['claimed_until'];
        if (is_string($claimedUntil) && $claimedUntil >= $now) {
            throw new ExternalEffectInProgress('The external effect is already in progress.');
        }

        throw new ExternalEffectOutcomeUnknown('A prior external-effect attempt did not record a completed outcome.');
    }

    public function markOutcomeUnknown(string $consumerName, string $messageId, string $claimToken): void
    {
        $connection = $this->connection;
        $connection->update(
            self::INBOX_TABLE,
            ['claimed_until' => null, 'claim_token' => null],
            ['consumer_name' => $consumerName, 'message_id' => $messageId, 'claim_token' => $claimToken],
        );
    }

    public function complete(
        string $consumerName,
        string $messageId,
        string $claimToken,
        DateTimeImmutable $processedAt,
    ): void {
        $processedAtValue = $processedAt->format('Y-m-d H:i:s.u');
        $connection       = $this->connection;
        $updated          = $connection->update(
            self::INBOX_TABLE,
            [
                'processed_at' => $processedAtValue,
                'claimed_until' => null,
                'claim_token' => null,
            ],
            ['consumer_name' => $consumerName, 'message_id' => $messageId, 'claim_token' => $claimToken],
        );
        if ($updated !== 1) {
            throw new RuntimeException('The external-effect inbox claim expired before completion.');
        }
    }
}
