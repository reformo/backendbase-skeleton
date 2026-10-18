<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency;

use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestMismatch;
use Backendbase\Shared\Idempotency\RequestOutstanding;
use Backendbase\Shared\Idempotency\RequestReplayStore;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use LogicException;
use RuntimeException;

use function hash_equals;
use function is_string;

final readonly class DoctrineRequestReplayStore implements RequestReplayStore
{
    public function __construct(private Connection $connection, private Clock $clock)
    {
    }

    public function claim(RequestIdentity $identity): string|null
    {
        $connection  = $this->connection();
        $now         = $this->timestamp();
        $keyHash     = $identity->keyHash;
        $fingerprint = $identity->fingerprint;
        $connection->executeStatement(
            'DELETE FROM http_request_replay WHERE request_hash = ? AND expires_at <= ? AND response IS NOT NULL',
            [$keyHash, $now],
        );
        try {
            $connection->insert('http_request_replay', [
                'request_hash' => $keyHash,
                'fingerprint' => $fingerprint,
                'created_at' => $now,
            ]);

            return null;
        } catch (UniqueConstraintViolationException) {
            return $this->existing($identity);
        }
    }

    public function complete(RequestIdentity $identity, string $response): void
    {
        $connection  = $this->connection();
        $clock       = $this->clock;
        $now         = $clock->now();
        $expires     = $now->modify('+24 hours');
        $keyHash     = $identity->keyHash;
        $fingerprint = $identity->fingerprint;
        $expiresAt   = $expires->format('Y-m-d H:i:s.u');
        $changed     = $connection->executeStatement(
            'UPDATE http_request_replay SET response = ?, expires_at = ? WHERE request_hash = ? AND fingerprint = ? AND response IS NULL',
            [$response, $expiresAt, $keyHash, $fingerprint],
        );
        if ((int) $changed !== 1) {
            throw new RuntimeException('The request claim could not be completed.');
        }
    }

    public function deleteExpired(): int
    {
        $connection = $this->connection();
        $now        = $this->timestamp();
        $keys       = $connection->fetchFirstColumn(
            'SELECT request_hash FROM http_request_replay WHERE expires_at <= ? ORDER BY expires_at LIMIT 500',
            [$now],
        );
        if ($keys === []) {
            return 0;
        }

        return (int) $connection->executeStatement(
            'DELETE FROM http_request_replay WHERE request_hash IN (?) AND expires_at <= ? AND response IS NOT NULL',
            [$keys, $now],
            [ArrayParameterType::STRING],
        );
    }

    private function existing(RequestIdentity $identity): string
    {
        $connection = $this->connection();
        $keyHash    = $identity->keyHash;
        $row        = $connection->fetchAssociative('SELECT fingerprint, response FROM http_request_replay WHERE request_hash = ?', [$keyHash]);
        if ($row === false || ! is_string($row['fingerprint'])) {
            throw new RuntimeException('The stored request claim is unavailable.');
        }

        if (! hash_equals($row['fingerprint'], $identity->fingerprint)) {
            throw new RequestMismatch('This key was used with different request content.');
        }

        if ($row['response'] === null) {
            throw new RequestOutstanding('This request is in progress or its outcome requires reconciliation.');
        }

        if (! is_string($row['response']) || $row['response'] === '') {
            throw new RuntimeException('The stored request response is invalid.');
        }

        return $row['response'];
    }

    private function connection(): Connection
    {
        $connection = $this->connection;
        if ($connection->isTransactionActive()) {
            throw new LogicException('Request claims must commit independently of application transactions.');
        }

        return $connection;
    }

    private function timestamp(): string
    {
        $clock = $this->clock;
        $now   = $clock->now();

        return $now->format('Y-m-d H:i:s.u');
    }
}
