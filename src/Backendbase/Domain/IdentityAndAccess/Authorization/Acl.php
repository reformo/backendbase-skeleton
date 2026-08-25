<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Authorization;

use Backendbase\Shared\Exception\ResourceAccessForbidden;

use function in_array;

readonly class Acl
{
    /** @param array<int, string> $privileges */
    public function __construct(private array $privileges)
    {
    }

    public function isAllowed(string $privilege, string|null $role = null): bool
    {
        if ($role === 'system-admin' || in_array('full-privileges', $this->privileges, true)) {
            return true;
        }

        if (! in_array($privilege, $this->privileges, true)) {
            throw ResourceAccessForbidden::create("You don't have the privilege to access the resource or perform the action");
        }

        return true;
    }
}
