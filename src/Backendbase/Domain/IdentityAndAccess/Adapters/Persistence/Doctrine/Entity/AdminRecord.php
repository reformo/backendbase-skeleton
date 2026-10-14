<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'identity_admin', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'identity_admin_account_unq', columns: ['account_id'])]
#[ORM\UniqueConstraint(name: 'identity_admin_active_login_unq', columns: ['active_login_name'])]
class AdminRecord extends ActorRecord
{
    public static function create(SystemAdmin $actor): self
    {
        $record = new self();
        $record->assignId($actor->id());
        $record->synchronize($actor);

        return $record;
    }

    public function toDomain(): SystemAdmin
    {
        return new SystemAdmin(IdentityId::fromString($this->id()), $this->snapshot());
    }
}
