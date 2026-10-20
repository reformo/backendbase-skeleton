<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;

use function is_array;
use function preg_match;

final readonly class SessionState
{
    public function __construct(
        public Principal $principal,
        public SessionPolicy $policy,
        public int $createdAt,
        public int $lastUsedAt,
        public int $maximumExpiresAt,
        public string $refreshHash,
    ) {
        if ($createdAt < 1 || $lastUsedAt < $createdAt || $maximumExpiresAt !== $policy->maximumExpiresAt($createdAt) || $lastUsedAt >= $maximumExpiresAt || preg_match('/\A[0-9a-f]{64}\z/D', $refreshHash) !== 1) {
            throw AuthenticationUnavailable::create('The persisted session state is invalid.');
        }
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        StateField::assertKnownFields($values, ['sessionVersion', 'accountId', 'sub', 'sid', 'jti', 'authVersion', 'realm', 'actorType', 'createdAt', 'lastUsedAt', 'maximumExpiresAt', 'refreshHash', 'policy']);
        $realm  = Realm::tryFrom(StateField::string($values, 'realm'));
        $policy = $values['policy'] ?? null;
        if (StateField::integer($values, 'sessionVersion') !== 2 || $realm === null || ! is_array($policy) || StateField::string($values, 'actorType') !== $realm->actorType()) {
            throw AuthenticationUnavailable::create('The persisted session state is invalid.');
        }

        StateField::assertKnownFields($policy, ['accessTokenLifetimeSeconds', 'idleTimeoutSeconds', 'maximumLifetimeSeconds']);
        $principal = new Principal($realm, [
            'accountId' => StateField::string($values, 'accountId'),
            'actorId' => StateField::string($values, 'sub'),
            'sid' => StateField::string($values, 'sid'),
            'jti' => StateField::string($values, 'jti'),
            'authVersion' => StateField::integer($values, 'authVersion'),
        ]);

        return new self($principal, new SessionPolicy(StateField::integer($policy, 'accessTokenLifetimeSeconds'), StateField::integer($policy, 'idleTimeoutSeconds'), StateField::integer($policy, 'maximumLifetimeSeconds')), StateField::integer($values, 'createdAt'), StateField::integer($values, 'lastUsedAt'), StateField::integer($values, 'maximumExpiresAt'), StateField::string($values, 'refreshHash'));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $principal = $this->principal;
        $policy    = $this->policy;

        $claims                     = $principal->claims();
        $claims['sessionVersion']   = 2;
        $claims['createdAt']        = $this->createdAt;
        $claims['lastUsedAt']       = $this->lastUsedAt;
        $claims['maximumExpiresAt'] = $this->maximumExpiresAt;
        $claims['refreshHash']      = $this->refreshHash;
        $claims['policy']           = $policy->toArray();

        return $claims;
    }
}
