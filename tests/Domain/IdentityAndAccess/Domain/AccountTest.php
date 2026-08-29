<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Domain;

use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    #[Test]
    public function itRegistersAndRevisesAnAccountProfile(): void
    {
        $account = Account::register(
            AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586'),
            new Email('first@example.com'),
            PasswordHash::create('first-password-hash'),
            new AccountPrivileges(['example.add', 'account.list']),
        );

        $account->revise(
            new Email('second@example.com'),
            PasswordHash::create('second-password-hash'),
            new AccountPrivileges(['account.list']),
        );

        self::assertSame('4bb3fe29-8b80-463e-9d42-b3a9298a7586', $account->id()->toString());
        self::assertSame('second@example.com', $account->email()->toString());
        self::assertSame('second-password-hash', $account->passwordHash()->toString());
        self::assertSame(['account.list'], $account->privileges()->slugs());
    }

    #[Test]
    public function itRetainsUnchangedAccountProfileValues(): void
    {
        $account = Account::register(
            AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586'),
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges(['example.add']),
        );

        $account->revise(null, null, null);

        self::assertSame('account@example.com', $account->email()->toString());
        self::assertSame('password-hash', $account->passwordHash()->toString());
        self::assertSame(['example.add'], $account->privileges()->slugs());
    }
}
