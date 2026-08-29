<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthentication;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository as AccountAuthenticationRepositoryContract;
use Doctrine\DBAL\Connection;

final readonly class DoctrineAccountAuthenticationRepository implements AccountAuthenticationRepositoryContract
{
    public function __construct(private Connection $connection)
    {
    }

    public function findByEmail(string $email): AccountAuthentication|null
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT account.uuid, account.email, account.password_hash, privilege.slug AS privilege_slug '
            . 'FROM example_accounts account '
            . 'LEFT JOIN example_account_privileged accountPrivilege '
            . 'ON accountPrivilege.account_id = account.id AND accountPrivilege.expired_at IS NULL '
            . 'LEFT JOIN example_privileges privilege '
            . 'ON privilege.id = accountPrivilege.privilege_id AND privilege.deleted_at IS NULL '
            . 'WHERE account.email = :email AND account.deleted_at IS NULL '
            . 'ORDER BY privilege.slug ASC',
            ['email' => $email],
        );

        return AccountAuthenticationMapper::account($rows);
    }
}
