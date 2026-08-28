<?php

declare(strict_types=1);

namespace Backendbase\Shared\Authorization;

interface AccessControl
{
    public function isAllowed(string $privilege, string|null $role = null): bool;
}
