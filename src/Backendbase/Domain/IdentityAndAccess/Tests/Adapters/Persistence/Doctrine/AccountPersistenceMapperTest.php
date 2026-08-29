<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\AccountAuthenticationMapper;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\AccountReadModelMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class AccountPersistenceMapperTest extends TestCase
{
    #[Test]
    public function itMapsAnAccountWithoutPrivileges(): void
    {
        $accounts = AccountReadModelMapper::list([
            [
                'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'email' => 'account@example.com',
                'created_at' => '2026-08-29 10:00:00.000000',
                'privilege_slug' => null,
            ],
        ]);

        self::assertSame([], $accounts[0]->privilegeSlugs());
    }

    #[Test]
    public function itRejectsInvalidAuthenticationData(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AccountAuthenticationMapper::account([
            [
                'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'email' => 'account@example.com',
                'password_hash' => '',
                'privilege_slug' => null,
            ],
        ]);
    }

    #[Test]
    public function itRejectsInvalidReadModelData(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AccountReadModelMapper::list([
            [
                'uuid' => '',
                'email' => 'account@example.com',
                'created_at' => '2026-08-29 10:00:00.000000',
                'privilege_slug' => null,
            ],
        ]);
    }

    #[Test]
    public function itRejectsAnInvalidCreationDate(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AccountReadModelMapper::list([
            [
                'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'email' => 'account@example.com',
                'created_at' => 'invalid-date',
                'privilege_slug' => null,
            ],
        ]);
    }
}
