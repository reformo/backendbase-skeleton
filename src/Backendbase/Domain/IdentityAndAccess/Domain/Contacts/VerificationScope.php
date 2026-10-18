<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class VerificationScope
{
    /** @param array{accountId: string|null, actorId: string|null} $owner */
    public function __construct(private Realm $realm, private array $owner = ['accountId' => null, 'actorId' => null])
    {
        if (($owner['accountId'] === null) !== ($owner['actorId'] === null)) {
            throw AuthenticationDenied::create('A verification context requires both Account and actor references.');
        }

        if ($realm === Realm::ADMIN && ($owner['accountId'] === null || $owner['actorId'] === null)) {
            throw AuthenticationDenied::create('An Admin verification context requires its Account and actor.');
        }
    }

    public function realm(): string
    {
        return $this->realm->value;
    }

    public function purpose(): string
    {
        if ($this->realm === Realm::ADMIN) {
            return 'admin-contact-reverification';
        }

        return $this->owner['accountId'] === null ? 'user-registration' : 'user-contact-verification';
    }

    /** @return array{accountId: string|null, actorId: string|null} */
    public function owner(): array
    {
        return $this->owner;
    }
}
