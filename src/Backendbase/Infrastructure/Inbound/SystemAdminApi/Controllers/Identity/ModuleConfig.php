<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\Adapters\Http\Identity\ContactRoutePolicy;
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
                ->setName('systemadminReadCurrentIdentity');

            $group->delete('/token', Handlers\RevokeAccessToken::class)
                ->setName('systemadminRevokeCurrentAccessToken')
                ->add(new RoutePolicy('revoke-token'));

            $group->delete('/session', Handlers\EndSession::class)
                ->setName('systemadminEndCurrentSession')
                ->add(new RoutePolicy('logout'));

            $group->delete('/account', Handlers\CloseAccount::class)
                ->setName('systemadminCloseAccount')
                ->add(new RoutePolicy('close-account'));

            $group->post('/contact-verifications', Handlers\RequestContactVerification::class)
                ->setName('systemadminAuthenticatedContactRequest')
                ->add(new ContactRoutePolicy(['action' => 'request', 'authenticated' => true]));

            $group->post('/contact-verifications/{contact-info-id}/confirm', Handlers\ConfirmContactVerification::class)
                ->setName('systemadminAuthenticatedContactConfirm')
                ->add(new ContactRoutePolicy(['action' => 'confirm', 'authenticated' => true]));

            $group->post('/contact-verifications/{contact-info-id}/resend', Handlers\ResendContactVerification::class)
                ->setName('systemadminAuthenticatedContactResend')
                ->add(new ContactRoutePolicy(['action' => 'resend', 'authenticated' => true]));
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
