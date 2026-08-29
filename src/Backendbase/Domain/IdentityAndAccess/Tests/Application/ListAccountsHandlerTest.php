<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\QueryHandlers\ListAccountsHandler;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\ListAccounts;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;
use Backendbase\Shared\Authorization\AccessControl;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListAccountsHandlerTest extends TestCase
{
    #[Test]
    public function itListsAccountsAfterAclAuthorization(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(ListAccountsHandler::REQUIRED_PRIVILEGE)
            ->willReturn(true);
        $accounts   = [
            new AccountListItem(
                '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'account@example.com',
                ['account.list'],
                new DateTimeImmutable('2026-08-29 10:00:00 UTC'),
            ),
        ];
        $repository = $this->createMock(AccountReadRepository::class);
        $repository->expects(self::once())->method('listActive')->willReturn($accounts);

        self::assertSame($accounts, (new ListAccountsHandler($repository))->handle(new ListAccounts($accessControl)));
    }
}
