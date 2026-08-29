<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Authorization;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AclTest extends TestCase
{
    #[Test]
    public function itAllowsExplicitAndAdministrativePrivileges(): void
    {
        self::assertTrue((new Acl(['read-example']))->isAllowed('read-example'));
        self::assertTrue((new Acl([]))->isAllowed('write-example', 'system-admin'));
        self::assertTrue((new Acl(['full-privileges']))->isAllowed('write-example'));
    }

    #[Test]
    public function itRejectsAMissingPrivilege(): void
    {
        $this->expectException(ResourceAccessForbidden::class);

        (new Acl([]))->isAllowed('read-example');
    }
}
