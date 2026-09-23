<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\ListAccounts;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Account\Handlers\Accounts;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\QueryBus;
use DateTimeImmutable;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class AccountReadControllersTest extends TestCase
{
    #[Test]
    public function itListsAccountsFromTheQueryResult(): void
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::once())
            ->method('handle')
            ->with(self::isInstanceOf(ListAccounts::class))
            ->willReturn([
                new AccountListItem(
                    '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                    'account@example.com',
                    ['account.list'],
                    new DateTimeImmutable('2026-08-29 10:00:00 UTC'),
                ),
            ]);
        $action  = new Accounts($queryBus, $this->createStub(LoggerInterface::class));
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/accounts')
            ->withAttribute(AccessControl::class, new Acl(['full-privileges']));

        $response = $action($request, new Response(), []);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('account@example.com', $payload['accounts'][0]['email']);
        self::assertSame(['account.list'], $payload['accounts'][0]['privilegeSlugs']);
        self::assertSame('2026-08-29T10:00:00+00:00', $payload['accounts'][0]['createdAt']);
    }
}
