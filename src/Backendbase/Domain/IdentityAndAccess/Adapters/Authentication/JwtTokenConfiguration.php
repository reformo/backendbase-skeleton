<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

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

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
        $this->configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($config['sign-key']),
        );
    }

    /**
     * @param non-empty-string $claimKey
     * @param non-empty-string $tokenId
     */
    public function issue(string $claimKey, mixed $claimValue, string $tokenId, DateTimeImmutable $issuedAt): string
    {
        $config = $this->config;

        return new JwtFacade(clock: new FrozenClock($issuedAt))->issue(
            $this->configuration->signer(),
            $this->configuration->signingKey(),
            static function (Builder $builder, DateTimeImmutable $issuedAt) use ($config, $tokenId, $claimKey, $claimValue) {
                return $builder
                    ->issuedBy($config['issuer'])
                    ->permittedFor($config['permitted-for'])
                    ->identifiedBy($tokenId)
                    ->withClaim($claimKey, $claimValue)
                    ->expiresAt($issuedAt->add(new DateInterval($config['duration'])));
            },
        )->toString();
    }

    public function expirationTime(DateTimeImmutable $issuedAt): DateTimeImmutable
    {
        return $issuedAt->add(new DateInterval($this->config['duration']));
    }

    /** @param non-empty-string $jwtToken */
    public function parse(string $jwtToken, Clock $clock): UnencryptedToken
    {
        return new JwtFacade()->parse(
            $jwtToken,
            new Constraint\SignedWith($this->configuration->signer(), $this->configuration->signingKey()),
            new Constraint\StrictValidAt($clock, new DateInterval(self::CLOCK_LEEWAY)),
        );
    }

    /** @param non-empty-string $tokenId */
    public function assertValid(UnencryptedToken $token, string $tokenId, Clock $clock): void
    {
        $configuration = $this->configuration->withValidationConstraints(
            new SignedWith($this->configuration->signer(), $this->configuration->signingKey()),
            new StrictValidAt($clock, new DateInterval(self::CLOCK_LEEWAY)),
            new IssuedBy($this->config['issuer']),
            new Constraint\IdentifiedBy($tokenId),
            new Constraint\PermittedFor($this->config['permitted-for']),
        );
        $configuration->validator()->assert($token, ...$configuration->validationConstraints());
    }

    /** @param non-empty-string $tokenId */
    public function tokenRedisKey(string $tokenId): string
    {
        return 'JWT:' . $this->config['alias'] . ':' . $tokenId;
    }

    public function userRedisKey(mixed $userId): string
    {
        return strtolower((string) $this->config['alias']) . ':' . $userId;
    }
}
