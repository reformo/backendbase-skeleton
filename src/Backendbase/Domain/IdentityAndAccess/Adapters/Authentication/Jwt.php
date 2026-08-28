<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Lcobucci\JWT\UnencryptedToken;
use Override;
use Throwable;

final readonly class Jwt implements TokenIssuer, TokenValidator
{
    public function __construct(
        private JwtTokenCodec $tokenCodec,
        private AuthorizationStore $authorizationStore,
    ) {
    }

    /** @param array<string, mixed> $data */
    #[Override]
    public function issueNewToken(string $claimKey, mixed $claimValue, array $data): string
    {
        $issuedToken = $this->tokenCodec->issue($claimKey, $claimValue);
        $this->authorizationStore->store($claimKey, $claimValue, $data, $issuedToken);

        return $issuedToken['token'];
    }

    /** @return array<string, mixed> */
    #[Override]
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
    #[Override]
    public function validateByUserId(string $userId): array
    {
        $tokenData = $this->authorizationStore->byUserId($userId);
        if (empty($tokenData)) {
            throw AuthorizationExpired::create('identity.authorization.invalid-token');
        }

        return $tokenData;
    }

    #[Override]
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
