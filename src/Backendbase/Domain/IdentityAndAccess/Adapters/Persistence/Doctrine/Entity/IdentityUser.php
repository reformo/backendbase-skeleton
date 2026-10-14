<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'identity_user', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'identity_user_account_unq', columns: ['account_id'])]
#[ORM\UniqueConstraint(name: 'identity_user_active_login_unq', columns: ['active_login_name'])]
class IdentityUser extends ActorRecord
{
    public static function create(User $actor): self
    {
        $record = new self();
        $record->assignId($actor->id());
        $record->synchronize($actor);

        return $record;
    }

    public function toDomain(): User
    {
        return new User(IdentityId::fromString($this->id()), $this->snapshot());
    }
}
