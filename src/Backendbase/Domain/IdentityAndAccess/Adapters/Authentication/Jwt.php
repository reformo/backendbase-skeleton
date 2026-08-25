<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\UnencryptedToken;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use Throwable;

class Jwt
{
    private readonly JwtTokenCodec $tokenCodec;
    private readonly JwtAuthorizationStore $authorizationStore;

    /** @param array<string, mixed> $config */
    public function __construct(RedisJsonInterface $redisJson, array $config, Clock|null $clock = null)
    {
        $configuration            = new JwtTokenConfiguration($config);
        $this->tokenCodec         = new JwtTokenCodec($configuration, $clock ?? SystemClock::fromUTC());
        $this->authorizationStore = new JwtAuthorizationStore($redisJson, $configuration);
    }

    /** @param array<string, mixed> $data */
    public function issueNewToken(string $claimKey, mixed $claimValue, array $data): string
    {
        $issuedToken = $this->tokenCodec->issue($claimKey, $claimValue);
        $this->authorizationStore->store($claimKey, $claimValue, $data, $issuedToken);

        return $issuedToken['token'];
    }

    /** @return array<string, mixed> */
    public function validateToken(string $jwtToken): array
    {
        try {
            $token = $this->tokenCodec->parse($jwtToken);
            $jti   = self::tokenId($token);
            if (! $this->authorizationStore->isActive($jti)) {
                throw AuthorizationExpired::create('identity.authorization.invalid-token');
            }

            $userId = $token->claims()->get('userId');
            $this->tokenCodec->assertValid($token, $jti);
            $tokenData = $this->authorizationStore->byUserId($userId);
            if (empty($tokenData)) {
                throw AuthorizationExpired::create('identity.authorization.invalid-token');
            }

            return $tokenData;
        } catch (Throwable $exception) {
            $this->revokeToken($jwtToken);

            throw AuthorizationExpired::create($exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    public function validateByUserId(string $userId): array
    {
        $tokenData = $this->authorizationStore->byUserId($userId);
        if (empty($tokenData)) {
            throw AuthorizationExpired::create('identity.authorization.invalid-token');
        }

        return $tokenData;
    }

    public function revokeToken(string $jwtToken): void
    {
        try {
            $token = $this->tokenCodec->parse($jwtToken);
            $this->authorizationStore->revoke(self::tokenId($token));
        } catch (Throwable $exception) {
            throw AuthorizationExpired::create($exception->getMessage());
        }
    }

    /** @return non-empty-string */
    private static function tokenId(UnencryptedToken $token): string
    {
        return JwtTokenCodec::tokenId($token);
    }
}
