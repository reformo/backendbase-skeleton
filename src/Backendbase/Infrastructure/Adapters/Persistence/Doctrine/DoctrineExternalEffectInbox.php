<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Persistence\ExternalEffectInProgress;
use Backendbase\Shared\Persistence\ExternalEffectOutcomeUnknown;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

use function is_string;

final readonly class DoctrineExternalEffectInbox implements ExternalEffectInbox
{
    private const string INBOX_TABLE = 'integration_event_inbox';

    public function __construct(
        private Connection $connection,
        private int $claimTtlSeconds = 300,
    ) {
    }

    public function processOnce(
        string $consumerName,
        string $messageId,
        string $effectName,
        callable $externalEffect,
    ): void {
        $claimToken = $this->claim($consumerName, $messageId, $effectName);
        if ($claimToken === null) {
            return;
        }

        try {
            $externalEffect();
        } catch (Throwable $exception) {
            $this->markOutcomeUnknown($consumerName, $messageId, $claimToken);

            throw new ExternalEffectOutcomeUnknown(
                'The external effect started, but its outcome is unknown.',
                previous: $exception,
            );
        }

        try {
            $this->complete($consumerName, $messageId, $claimToken);
        } catch (Throwable $exception) {
            throw new ExternalEffectOutcomeUnknown(
                'The external effect completed, but its completion could not be recorded.',
                previous: $exception,
            );
        }
    }

    private function claim(string $consumerName, string $messageId, string $effectName): string|null
    {
        $now          = DateTimeImmutable::create();
        $claimToken   = Uuid::uuid7()->toString();
        $claimedUntil = $now->modify('+' . $this->claimTtlSeconds . ' seconds');

        return $this->connection->transactional(static function (Connection $connection) use (
            $consumerName,
            $messageId,
            $effectName,
            $now,
            $claimToken,
            $claimedUntil,
        ): string|null {
            try {
                $connection->insert(self::INBOX_TABLE, [
                    'consumer_name' => $consumerName,
                    'message_id' => $messageId,
                    'event_name' => $effectName,
                    'received_at' => $now->format('Y-m-d H:i:s.u'),
                    'processed_at' => null,
                    'claimed_until' => $claimedUntil->format('Y-m-d H:i:s.u'),
                    'claim_token' => $claimToken,
                ]);

                return $claimToken;
            } catch (UniqueConstraintViolationException) {
                return self::existingClaim(
                    $connection,
                    $consumerName,
                    $messageId,
                    $now->format('Y-m-d H:i:s.u'),
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

    private function markOutcomeUnknown(string $consumerName, string $messageId, string $claimToken): void
    {
        $this->connection->update(
            self::INBOX_TABLE,
            ['claimed_until' => null, 'claim_token' => null],
            ['consumer_name' => $consumerName, 'message_id' => $messageId, 'claim_token' => $claimToken],
        );
    }

    private function complete(string $consumerName, string $messageId, string $claimToken): void
    {
        $updated = $this->connection->update(
            self::INBOX_TABLE,
            [
                'processed_at' => DateTimeImmutable::create()->format('Y-m-d H:i:s.u'),
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
