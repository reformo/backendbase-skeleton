<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Shared\Configuration\JwtSettings;
use DateInterval;
use DateTimeImmutable;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

use function strtolower;

final class JwtTokenConfiguration
{
    private const string CLOCK_LEEWAY = 'PT30S';

    private readonly Configuration $configuration;

    public function __construct(private readonly JwtSettings $settings)
    {
        $signingKey          = $this->settings->signingKey();
        $this->configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($signingKey),
        );
    }

    /**
     * @param non-empty-string $claimKey
     * @param non-empty-string $tokenId
     */
    public function issue(string $claimKey, mixed $claimValue, string $tokenId, DateTimeImmutable $issuedAt): string
    {
        $issuer       = $this->settings->issuer();
        $permittedFor = $this->settings->permittedFor();
        $duration     = $this->settings->duration();
        $signer       = $this->configuration->signer();
        $signingKey   = $this->configuration->signingKey();

        return new JwtFacade(clock: new FrozenClock($issuedAt))->issue(
            $signer,
            $signingKey,
            static function (
                Builder $builder,
                DateTimeImmutable $issuedAt,
            ) use (
                $issuer,
                $permittedFor,
                $duration,
                $tokenId,
                $claimKey,
                $claimValue,
            ) {
                return $builder
                    ->issuedBy($issuer)
                    ->permittedFor($permittedFor)
                    ->identifiedBy($tokenId)
                    ->withClaim($claimKey, $claimValue)
                    ->expiresAt($issuedAt->add($duration));
            },
        )->toString();
    }

    public function expirationTime(DateTimeImmutable $issuedAt): DateTimeImmutable
    {
        $duration = $this->settings->duration();

        return $issuedAt->add($duration);
    }

    /** @param non-empty-string $jwtToken */
    public function parse(string $jwtToken, Clock $clock): UnencryptedToken
    {
        $signer     = $this->configuration->signer();
        $signingKey = $this->configuration->signingKey();

        return new JwtFacade()->parse(
            $jwtToken,
            new Constraint\SignedWith($signer, $signingKey),
            new Constraint\StrictValidAt($clock, new DateInterval(self::CLOCK_LEEWAY)),
        );
    }

    /** @param non-empty-string $tokenId */
    public function assertValid(UnencryptedToken $token, string $tokenId, Clock $clock): void
    {
        $issuer        = $this->settings->issuer();
        $permittedFor  = $this->settings->permittedFor();
        $signer        = $this->configuration->signer();
        $signingKey    = $this->configuration->signingKey();
        $configuration = $this->configuration->withValidationConstraints(
            new SignedWith($signer, $signingKey),
            new StrictValidAt($clock, new DateInterval(self::CLOCK_LEEWAY)),
            new IssuedBy($issuer),
            new Constraint\IdentifiedBy($tokenId),
            new Constraint\PermittedFor($permittedFor),
        );
        $validator     = $configuration->validator();
        $constraints   = $configuration->validationConstraints();
        $validator->assert($token, ...$constraints);
    }

    /** @param non-empty-string $tokenId */
    public function tokenRedisKey(string $tokenId): string
    {
        $alias = $this->settings->alias();

        return 'JWT:' . $alias . ':' . $tokenId;
    }

    public function userRedisKey(mixed $userId): string
    {
        $alias = $this->settings->alias();

        return strtolower($alias) . ':' . $userId;
    }
}
