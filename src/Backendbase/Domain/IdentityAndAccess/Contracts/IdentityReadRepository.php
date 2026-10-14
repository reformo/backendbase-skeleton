<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ActorHistory;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

interface IdentityReadRepository
{
    public function actorHistory(Realm $realm, string $actorId): ActorHistory|null;
}
