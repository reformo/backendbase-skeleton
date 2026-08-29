<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\QueryHandlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\ListAccounts;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;
use Override;

/** @implements QueryHandler<ListAccounts, list<AccountListItem>> */
final readonly class ListAccountsHandler implements QueryHandler
{
    public const string REQUIRED_PRIVILEGE = 'account.list';

    public function __construct(private AccountReadRepository $accountRepository)
    {
    }

    /**
     * @param ListAccounts $query
     *
     * @return list<AccountListItem>
     */
    #[Override]
    public function handle(Query $query): array
    {
        $query->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);

        return $this->accountRepository->listActive();
    }
}
