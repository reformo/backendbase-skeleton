<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthorizationStore;
use DateTimeImmutable;
use Override;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function array_key_exists;
use function array_walk;

use const DATE_ATOM;

/** @phpstan-import-type IssuedToken from AuthorizationStore */
final class JwtAuthorizationStore implements AuthorizationStore
{
    public function __construct(
        private readonly RedisJsonInterface $redisJson,
        private readonly JwtTokenConfiguration $configuration,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param IssuedToken          $issuedToken
     */
    #[Override]
    public function store(string $claimKey, mixed $claimValue, array $data, array $issuedToken): void
    {
        $userRedisKey = $this->configuration->userRedisKey($claimValue);
        $userData     = $this->redisJson->get($userRedisKey);
        if (empty($userData)) {
            $userData = ['tokens' => []];
        }

        if (array_key_exists('tokens', $userData)) {
            $userData['tokens'][] = [
                'token' => $issuedToken['token'],
                'jti' => $issuedToken['id'],
                'issuedAt' => $issuedToken['issuedAt']->format(DATE_ATOM),
                'willExpireAt' => $issuedToken['expiresAt']->format(DATE_ATOM),
            ];
        } else {
            $userData['tokens'] = [$issuedToken['token']];
        }

        $privileges = [];
        if (array_key_exists('privileges', $data)) {
            $privileges = $data['privileges'];
            unset($data['privileges']);
        }

        $data['claimKey']       = $claimKey;
        $data['token']          = $issuedToken['token'];
        $data['willExpireAt']   = $issuedToken['expiresAt']->format(DATE_ATOM);
        $userData['privileges'] = $privileges;
        $userData['user']       = $data;
        $userData['claimKey']   = $claimKey;
        array_walk($data, static function (&$value): void {
            if (! ($value instanceof DateTimeImmutable)) {
                return;
            }

            $value = $value->format(DATE_ATOM);
        });

        $timeToLive = $issuedToken['expiresAt']->getTimestamp() - $issuedToken['issuedAt']->getTimestamp();
        $tokenKey   = $this->configuration->tokenRedisKey($issuedToken['id']);
        $this->redisJson->getClient()->set($tokenKey, '1', $timeToLive);
        $this->redisJson->set($userRedisKey, '.', $userData);
    }

    /** @param non-empty-string $tokenId */
    #[Override]
    public function isActive(string $tokenId): bool
    {
        return ! empty($this->redisJson->getClient()->get($this->configuration->tokenRedisKey($tokenId)));
    }

    /** @return array<string, mixed>|null */
    #[Override]
    public function byUserId(mixed $userId): array|null
    {
        return $this->redisJson->get($this->configuration->userRedisKey($userId));
    }

    /** @param non-empty-string $tokenId */
    #[Override]
    public function revoke(string $tokenId): void
    {
        $this->redisJson->getClient()->del($this->configuration->tokenRedisKey($tokenId));
    }
}
