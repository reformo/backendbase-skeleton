<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use DateTimeImmutable;

final class User extends Actor
{
    public function realm(): Realm
    {
        return Realm::USER;
    }

    public static function create(IdentityId $id, IdentityId $accountId, LoginName $loginName, DateTimeImmutable $now): self
    {
        return new self($id, ['accountId' => $accountId->toString(), 'loginName' => $loginName->toString(), 'approved' => true, 'invitation' => null, 'approval' => 'registration', 'deletedAt' => null, 'revision' => 1, 'createdAt' => $now->format('Y-m-d H:i:s.u')]);
    }
}
