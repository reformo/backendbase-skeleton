<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthorizationState;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Override;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use UnexpectedValueException;

use function is_array;

use const DATE_ATOM;

/** @phpstan-import-type IssuedToken from AuthorizationStore */
final class JwtAuthorizationStore implements AccountAuthorizationState, AuthorizationStore
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
    public function store(string $claimKey, string $claimValue, array $data, array $issuedToken): void
    {
        $userRedisKey = $this->configuration->userRedisKey($claimValue);
        $userData     = $this->redisJson->get($userRedisKey);
        if (empty($userData)) {
            $userData = ['tokens' => []];
        }

        if (! is_array($userData)) {
            throw new UnexpectedValueException('The stored authorization state must be an object.');
        }

        $tokens = $userData['tokens'] ?? [];
        if (! is_array($tokens)) {
            throw new UnexpectedValueException('The stored authorization token list must be an array.');
        }

        $tokens[]   = [
            'token' => $issuedToken['token'],
            'jti' => $issuedToken['id'],
            'issuedAt' => $issuedToken['issuedAt']->format(DATE_ATOM),
            'willExpireAt' => $issuedToken['expiresAt']->format(DATE_ATOM),
        ];
        $privileges = AuthorizationStateData::privileges($data);
        unset($data['privileges']);
        $data = AuthorizationStateData::formatDates($data);

        $data['claimKey']       = $claimKey;
        $data['token']          = $issuedToken['token'];
        $data['willExpireAt']   = $issuedToken['expiresAt']->format(DATE_ATOM);
        $userData['tokens']     = $tokens;
        $userData['privileges'] = $privileges;
        $userData['user']       = $data;
        $userData['claimKey']   = $claimKey;

        $timeToLive = $issuedToken['expiresAt']->getTimestamp() - $issuedToken['issuedAt']->getTimestamp();
        $tokenKey   = $this->configuration->tokenRedisKey($issuedToken['id']);
        $this->redisJson->getClient()->set($tokenKey, '1', $timeToLive);
        $this->redisJson->set($userRedisKey, '.', $userData);
        $this->redisJson->getClient()->expire($userRedisKey, $timeToLive);
    }

    /** @param non-empty-string $tokenId */
    #[Override]
    public function isActive(string $tokenId): bool
    {
        return ! empty($this->redisJson->getClient()->get($this->configuration->tokenRedisKey($tokenId)));
    }

    /** @return array<string, mixed>|null */
    #[Override]
    public function byUserId(string $userId): array|null
    {
        return $this->redisJson->get($this->configuration->userRedisKey($userId));
    }

    /** @param non-empty-string $tokenId */
    #[Override]
    public function revoke(string $tokenId): void
    {
        $this->redisJson->getClient()->del($this->configuration->tokenRedisKey($tokenId));
    }

    #[Override]
    public function revokeAll(AccountId $accountId): void
    {
        $userRedisKey = $this->configuration->userRedisKey($accountId->toString());
        $userData     = $this->redisJson->get($userRedisKey);
        $this->redisJson->del($userRedisKey);

        foreach (AuthorizationStateData::tokenIdentifiers($userData) as $tokenIdentifier) {
            $tokenKey = $this->configuration->tokenRedisKey($tokenIdentifier);
            $this->redisJson->getClient()->del($tokenKey);
        }
    }
}
