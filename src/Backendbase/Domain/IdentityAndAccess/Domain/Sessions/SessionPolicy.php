<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Sessions;

use DateTimeImmutable;
use InvalidArgumentException;

use function min;

final readonly class SessionPolicy
{
    /** @var array{accessTokenLifetimeSeconds: int, idleTimeoutSeconds: int, maximumLifetimeSeconds: int} */
    private array $durations;

    public function __construct(int $accessTokenLifetimeSeconds, int $idleTimeoutSeconds, int $maximumLifetimeSeconds)
    {
        if ($accessTokenLifetimeSeconds < 60 || $accessTokenLifetimeSeconds > 3600 || $idleTimeoutSeconds < 60 || $maximumLifetimeSeconds < $idleTimeoutSeconds || $maximumLifetimeSeconds > 31536000) {
            throw new InvalidArgumentException('Session durations require a 60–3600 second token lifetime and 60 seconds through one year for idle and maximum lifetimes. Maximum must cover idle.');
        }

        $this->durations = ['accessTokenLifetimeSeconds' => $accessTokenLifetimeSeconds, 'idleTimeoutSeconds' => $idleTimeoutSeconds, 'maximumLifetimeSeconds' => $maximumLifetimeSeconds];
    }

    public function tokenExpiresAt(DateTimeImmutable $now, int $maximumExpiresAt): DateTimeImmutable
    {
        $timestamp = $now->getTimestamp();
        $durations = $this->durations;

        return $now->setTimestamp(min($timestamp + $durations['accessTokenLifetimeSeconds'], $maximumExpiresAt));
    }

    public function maximumExpiresAt(int $createdAt): int
    {
        return $createdAt + $this->durations['maximumLifetimeSeconds'];
    }

    public function idleExpiresAt(int $lastUsedAt, int $maximumExpiresAt): int
    {
        return min($lastUsedAt + $this->durations['idleTimeoutSeconds'], $maximumExpiresAt);
    }

    /** @return array{accessTokenLifetimeSeconds: int, idleTimeoutSeconds: int, maximumLifetimeSeconds: int} */
    public function toArray(): array
    {
        return $this->durations;
    }
}
