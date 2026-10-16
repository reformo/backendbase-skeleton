<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Administration;

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
    public const string ROUTE_KEY = 'administration';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    #[Override]
    public function __invoke(RouteCollectorProxy $routeCollector): void
    {
        $routeCollector->group('', function (RouteCollectorProxy $group): void {
            $group->post('/admins/invite', Handlers\InviteAdmin::class)
                ->setName('systemadminInviteAdmin')
                ->add(new RoutePolicy('invite-admin'));

            $group->post('/admins/approve', Handlers\ApproveAdmin::class)
                ->setName('systemadminApproveAdmin')
                ->add(new RoutePolicy('approve-admin'));

            $group->post('/actors/change', Handlers\ChangeActor::class)
                ->setName('systemadminRemoveOrRestoreActor')
                ->add(new RoutePolicy('change-actor'));

            $group->get('/actors/{realm}/{actor-id}', Handlers\ActorHistory::class)
                ->setName('systemadminReadActorHistory');
        })
            ->add(RealmAuthentication::class)
            ->add(new RealmPolicy(Realm::ADMIN));
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
