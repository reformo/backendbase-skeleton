<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\Adapters\Http\Identity\RealmAuthentication;
use Backendbase\Infrastructure\Adapters\Http\Identity\RealmPolicy;
use Backendbase\Infrastructure\Adapters\Http\Identity\RoutePolicy;
use Override;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;

final class ModuleConfig implements ModuleRoute
{
    public const string ROUTE_KEY = 'identity';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    #[Override]
    public function __invoke(RouteCollectorProxy $routeCollector): void
    {
        $routeCollector->group('', function (RouteCollectorProxy $group): void {
            $group->get('', Handlers\CurrentIdentity::class)
                ->setName('exampleReadCurrentIdentity');

            $group->delete('/token', Handlers\RevokeAccessToken::class)
                ->setName('exampleRevokeCurrentAccessToken')
                ->add(new RoutePolicy('revoke-token'));

            $group->delete('/session', Handlers\EndSession::class)
                ->setName('exampleEndCurrentSession')
                ->add(new RoutePolicy('logout'));

            $group->delete('/account', Handlers\CloseAccount::class)
                ->setName('exampleCloseAccount')
                ->add(new RoutePolicy('close-account'));
        })
            ->add(RealmAuthentication::class)
            ->add(new RealmPolicy(Realm::USER));
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
