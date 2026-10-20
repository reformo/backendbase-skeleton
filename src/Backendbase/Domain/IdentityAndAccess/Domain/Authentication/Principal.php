<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class Principal
{
    public static function start(Realm $realm, string $accountId, string $actorId, int $authVersion): self
    {
        $sessionId         = IdentityId::create();
        $tokenId           = IdentityId::create();
        $sessionIdentifier = $sessionId->toString();
        $tokenIdentifier   = $tokenId->toString();

        return new self($realm, ['accountId' => $accountId, 'actorId' => $actorId, 'sid' => $sessionIdentifier, 'jti' => $tokenIdentifier, 'authVersion' => $authVersion]);
    }

    /** @var array{accountId: non-empty-string, actorId: non-empty-string, sid: non-empty-string, jti: non-empty-string, authVersion: int} */
    private array $identity;

    /** @param array{accountId: string, actorId: string, sid: string, jti: string, authVersion: int} $identity */
    public function __construct(private Realm $realm, array $identity)
    {
        if ($identity['authVersion'] < 1) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $this->identity = [
            'accountId' => self::canonicalId($identity['accountId']),
            'actorId' => self::canonicalId($identity['actorId']),
            'sid' => self::canonicalId($identity['sid']),
            'jti' => self::canonicalId($identity['jti']),
            'authVersion' => $identity['authVersion'],
        ];
    }

    public function realm(): Realm
    {
        return $this->realm;
    }

    /** @return array{accountId: non-empty-string, actorId: non-empty-string, sid: non-empty-string, jti: non-empty-string, authVersion: int} */
    public function identity(): array
    {
        return $this->identity;
    }

    /** @return array{accountId: non-empty-string, sub: non-empty-string, sid: non-empty-string, jti: non-empty-string, authVersion: int, realm: string, actorType: string} */
    public function claims(): array
    {
        $currentRealm = $this->realm;

        return [
            'accountId' => $this->identity['accountId'],
            'sub' => $this->identity['actorId'],
            'sid' => $this->identity['sid'],
            'jti' => $this->identity['jti'],
            'authVersion' => $this->identity['authVersion'],
            'realm' => $currentRealm->value,
            'actorType' => $currentRealm->actorType(),
        ];
    }

    public function assertAdmin(): void
    {
        if ($this->realm !== Realm::ADMIN) {
            throw AuthenticationDenied::create('The ADMIN realm is required.');
        }
    }

    /** @return non-empty-string */
    private static function canonicalId(string $value): string
    {
        $identifier = IdentityId::fromString($value);
        if ($value === '' || $identifier->toString() !== $value) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        return $value;
    }
}
