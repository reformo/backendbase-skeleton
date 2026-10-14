<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Throwable;

use function is_int;
use function is_string;
use function strlen;

final readonly class RealmTokenCodec implements TokenCodec
{
    public function __construct(private IdentitySettings $settings, private Clock $clock)
    {
    }

    public function encode(Principal $principal, DateTimeImmutable $expiresAt): string
    {
        $settings      = $this->settings;
        $policy        = $settings->realm($principal->realm());
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($policy['keys'][$policy['keyId']]));
        $clock         = $this->clock;
        $now           = $clock->now();
        $builder       = $configuration->builder();
        $builder       = $builder->withHeader('kid', $policy['keyId']);
        $builder       = $builder->withHeader('typ', 'JWT');
        $builder       = $builder->issuedBy($policy['issuer']);
        $builder       = $builder->permittedFor($policy['audience']);
        $builder       = $builder->issuedAt($now);
        $builder       = $builder->canOnlyBeUsedAfter($now);
        $builder       = $builder->expiresAt($expiresAt);
        $identity      = $principal->identity();
        $builder       = $builder->identifiedBy($identity['jti']);
        $builder       = $builder->relatedTo($identity['actorId']);
        foreach (['accountId', 'sid', 'authVersion', 'realm', 'actorType'] as $name) {
            $claims  = $principal->claims();
            $builder = $builder->withClaim($name, $claims[$name]);
        }

        $token = $builder->getToken($configuration->signer(), $configuration->signingKey());

        return $token->toString();
    }

    public function decode(Realm $expectedRealm, string $token): Principal
    {
        $settings      = $this->settings;
        $policy        = $settings->realm($expectedRealm);
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($policy['keys'][$policy['keyId']]));
        try {
            if (strlen($token) > 8192 || $token === '') {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $parser  = $configuration->parser();
            $parsed  = $parser->parse($token);
            $headers = $parsed->headers();
            $keyId   = $headers->get('kid');
            if (! is_string($keyId) || ! isset($policy['keys'][$keyId]) || $headers->get('typ') !== 'JWT') {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $clock     = $this->clock;
            $now       = $clock->now();
            $validator = $configuration->validator();
            $validator->assert(
                $parsed,
                new SignedWith(new Sha256(), InMemory::plainText($policy['keys'][$keyId])),
                new IssuedBy($policy['issuer']),
                new PermittedFor($policy['audience']),
                new StrictValidAt(new FrozenClock($now)),
            );
            $claims = $parsed instanceof UnencryptedToken ? $parsed->claims() : throw AuthenticationDenied::create('Authentication is not permitted.');

            return self::principal($expectedRealm, $claims->all());
        } catch (Throwable $exception) {
            throw AuthenticationDenied::create('Authentication is not permitted.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $claims */
    private static function principal(Realm $realm, array $claims): Principal
    {
        if (($claims['realm'] ?? null) !== $realm->value || ($claims['actorType'] ?? null) !== $realm->actorType() || ! is_int($claims['authVersion'] ?? null)) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $identity = ['authVersion' => $claims['authVersion']];
        foreach (['accountId' => 'accountId', 'actorId' => 'sub', 'sid' => 'sid', 'jti' => 'jti'] as $field => $name) {
            $value = $claims[$name] ?? null;
            if (! is_string($value)) {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $identity[$field] = $value;
        }

        return new Principal($realm, $identity);
    }
}
