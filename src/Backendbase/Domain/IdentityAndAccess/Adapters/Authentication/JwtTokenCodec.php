<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\Clock\Clock;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;

use function assert;
use function base64_encode;
use function is_string;
use function random_bytes;
use function rtrim;
use function strtr;
use function trim;

final class JwtTokenCodec
{
    public function __construct(
        private readonly JwtTokenConfiguration $configuration,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{token: string, id: non-empty-string, issuedAt: DateTimeImmutable, expiresAt: DateTimeImmutable} */
    public function issue(string $claimKey, mixed $claimValue): array
    {
        if ($claimKey === '') {
            throw new InvalidArgumentException('JWT claim key cannot be empty.');
        }

        $issuedAt = $this->clock->now();
        $tokenId  = self::generateTokenId();

        return [
            'token' => $this->configuration->issue($claimKey, $claimValue, $tokenId, $issuedAt),
            'id' => $tokenId,
            'issuedAt' => $issuedAt,
            'expiresAt' => $this->configuration->expirationTime($issuedAt),
        ];
    }

    public function parse(string $jwtToken): UnencryptedToken
    {
        if ($jwtToken === '') {
            throw AuthorizationExpired::create('identity.authorization.invalid-token');
        }

        return $this->configuration->parse($jwtToken, $this->clock);
    }

    /** @param non-empty-string $tokenId */
    public function assertValid(UnencryptedToken $token, string $tokenId): void
    {
        $this->configuration->assertValid($token, $tokenId, $this->clock);
    }

    /** @return non-empty-string */
    public static function tokenId(UnencryptedToken $token): string
    {
        $tokenId = $token->claims()->get(RegisteredClaims::ID);
        if (! is_string($tokenId) || trim($tokenId) === '') {
            throw AuthorizationExpired::create('identity.authorization.invalid-token');
        }

        return $tokenId;
    }

    /** @return non-empty-string */
    private static function generateTokenId(): string
    {
        $tokenId = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        assert($tokenId !== '');

        return $tokenId;
    }
}
