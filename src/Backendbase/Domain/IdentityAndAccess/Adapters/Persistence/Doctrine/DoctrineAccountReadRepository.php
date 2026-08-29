<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository as AccountReadRepositoryContract;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;
use Doctrine\DBAL\Connection;

final readonly class DoctrineAccountReadRepository implements AccountReadRepositoryContract
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<AccountListItem> */
    public function listActive(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT account.uuid, account.email, account.created_at, privilege.slug AS privilege_slug '
            . 'FROM example_accounts account '
            . 'LEFT JOIN example_account_privileged accountPrivilege '
            . 'ON accountPrivilege.account_id = account.id AND accountPrivilege.expired_at IS NULL '
            . 'LEFT JOIN example_privileges privilege '
            . 'ON privilege.id = accountPrivilege.privilege_id AND privilege.deleted_at IS NULL '
            . 'WHERE account.deleted_at IS NULL '
            . 'ORDER BY account.email ASC, privilege.slug ASC',
        );

        return AccountReadModelMapper::list($rows);
    }
}
