<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Contracts;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RegisterAccount;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RetireAccount;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\ReviseAccount;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\ListAccounts;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountCommandsAndQueryTest extends TestCase
{
    #[Test]
    public function itSerializesAccountCommandsWithoutPasswordHashes(): void
    {
        $accountId = AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586');
        $access    = new Acl(['full-privileges']);
        $register  = new RegisterAccount(
            $accountId,
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges(['example.add']),
            $access,
        );
        $revise    = new ReviseAccount($accountId, null, null, null, $access);
        $retire    = new RetireAccount($accountId, $access);

        self::assertSame([
            'accountId' => $accountId->toString(),
            'email' => 'account@example.com',
            'privilegeSlugs' => ['example.add'],
        ], $register->toArray());
        self::assertSame($register->toArray(), $register->jsonSerialize());
        self::assertSame([
            'accountId' => $accountId->toString(),
            'email' => null,
            'privilegeSlugs' => null,
        ], $revise->toArray());
        self::assertSame($revise->toArray(), $revise->jsonSerialize());
        self::assertSame(['accountId' => $accountId->toString()], $retire->toArray());
        self::assertSame($retire->toArray(), $retire->jsonSerialize());
    }

    #[Test]
    public function itExposesAnEmptyListAccountsQueryPayload(): void
    {
        $query = new ListAccounts(new Acl(['full-privileges']));

        self::assertSame([], $query->toArray());
        self::assertSame($query->toArray(), $query->jsonSerialize());
    }
}
