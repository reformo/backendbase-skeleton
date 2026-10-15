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
        return $this->realm === Realm::USER ? 'user-registration' : 'admin-contact-reverification';
    }

    /** @return array{accountId: string|null, actorId: string|null} */
    public function owner(): array
    {
        return $this->owner;
    }
}
