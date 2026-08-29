<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account;

use Backendbase\Domain\IdentityAndAccess\Adapters\Http\AuthorizationMiddleware;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\Handlers\Accounts;
use Backendbase\Shared\Http\Actions\ModuleRoute;
use Override;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;

final class ModuleConfig implements ModuleRoute
{
    public const string ROUTE_KEY = 'accounts';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    #[Override]
    public function __invoke(RouteCollectorProxy $routeCollector): void
    {
        $routeCollector->group('', function (RouteCollectorProxy $group): void {
            $group->get('', Accounts::class)->setName('listAccounts');
            $group->post('', Handlers\RegisterAccount::class)->setName('registerAccount');
            $group->patch('/{account-uuid}', Handlers\ReviseAccount::class)->setName('reviseAccount');
            $group->delete('/{account-uuid}', Handlers\RetireAccount::class)->setName('retireAccount');
        })->add(AuthorizationMiddleware::class);
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
