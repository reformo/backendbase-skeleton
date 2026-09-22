<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class AccountRepositoryContract extends TestCase
{
    use AccountLockRepositoryContract;

    abstract protected function authenticationRepository(): AccountAuthenticationRepository;

    abstract protected function readRepository(): AccountReadRepository;

    abstract protected function writeRepository(): AccountWriteRepository;

    #[Test]
    public function itStartsWithoutAccounts(): void
    {
        self::assertSame([], $this->readRepository()->listActive());
        self::assertNull($this->authenticationRepository()->findByEmail('missing@example.com'));
    }

    #[Test]
    public function itRegistersAndReadsAnActiveAccount(): void
    {
        $this->writeRepository()->register($this->account());

        $account = $this->writeRepository()->getActive($this->accountId());
        $listed  = $this->readRepository()->listActive();
        $login   = $this->authenticationRepository()->findByEmail('account@example.com');
        self::assertSame(['account.list'], $account->privileges()->slugs());
        self::assertSame('account@example.com', $listed[0]->email());
        self::assertSame(['account.list'], $listed[0]->privilegeSlugs());
        self::assertNotNull($login);
        self::assertSame('password-hash', $login->passwordHash());
    }

    #[Test]
    public function itRevisesAnActiveAccount(): void
    {
        $this->writeRepository()->register($this->account());
        $account = $this->writeRepository()->getActive($this->accountId());
        $account->revise(
            new Email('revised@example.com'),
            PasswordHash::create('revised-password-hash'),
            new AccountPrivileges(['account.retire']),
        );

        $this->writeRepository()->save($account);

        $revised = $this->writeRepository()->getActive($this->accountId());
        self::assertSame(['account.retire'], $revised->privileges()->slugs());
        $login = $this->authenticationRepository()->findByEmail('revised@example.com');
        self::assertNotNull($login);
        self::assertSame(['account.retire'], $login->privileges());
        self::assertNull($this->authenticationRepository()->findByEmail('account@example.com'));

        $revised->revise(null, null, new AccountPrivileges(['example.add']));
        $this->writeRepository()->save($revised);
        $login = $this->authenticationRepository()->findByEmail('revised@example.com');
        self::assertNotNull($login);
        self::assertSame(['example.add'], $login->privileges());
    }

    #[Test]
    public function itRetiresAnAccountAndAllowsAnEmailReplacement(): void
    {
        $this->writeRepository()->register($this->account());
        $account = $this->writeRepository()->getActive($this->accountId());
        $account->retire();
        $this->writeRepository()->save($account);

        self::assertSame([], $this->readRepository()->listActive());
        self::assertNull($this->authenticationRepository()->findByEmail('account@example.com'));
        try {
            $this->writeRepository()->getActive($this->accountId());
            self::fail('A retired account must not be active.');
        } catch (ResourceNotFound) {
            self::addToAssertionCount(1);
        }

        $replacement = $this->account('5bb3fe29-8b80-463e-9d42-b3a9298a7586');
        $this->writeRepository()->register($replacement);
        self::assertNotNull($this->authenticationRepository()->findByEmail('account@example.com'));
    }

    #[Test]
    public function itRejectsASecondActiveAccountWithTheSameEmail(): void
    {
        $this->writeRepository()->register($this->account());

        $this->expectException(AccountAlreadyRegistered::class);

        $this->writeRepository()->register($this->account('5bb3fe29-8b80-463e-9d42-b3a9298a7586'));
    }

    protected function account(string $id = '4bb3fe29-8b80-463e-9d42-b3a9298a7586'): Account
    {
        return Account::register(
            AccountId::fromString($id),
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges(['account.list']),
        );
    }

    protected function accountId(): AccountId
    {
        return AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586');
    }
}
