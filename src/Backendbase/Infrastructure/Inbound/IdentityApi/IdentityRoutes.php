<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\RealmAuthentication;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\RoutePolicy;
use Psr\Container\ContainerInterface;
use Slim\App;

final class IdentityRoutes
{
    /**
     * @param App<T> $app
     *
     * @template T of ContainerInterface|null
     */
    public static function register(App $app, Realm $realm): void
    {
        $app->get('/_status', Controllers\Liveness::class);
        $app->get('/_status/ready', Controllers\Readiness::class);
        $anonymous = ['/auth/login' => 'login'];
        if ($realm === Realm::USER) {
            $anonymous['/auth/register'] = 'register';
        }

        foreach ($anonymous as $path => $action) {
            $route = $app->post($path, Controllers\Authentication::class);
            $route->add(new RoutePolicy($realm, $action));
        }

        if ($realm === Realm::ADMIN) {
            self::administration($app);
        }

        $route = $app->get('/identity', Controllers\CurrentIdentity::class);
        $route->add(RealmAuthentication::class);
        $route->add(new RoutePolicy($realm));
        foreach (['/identity/token' => 'revoke-token', '/identity/session' => 'logout', '/identity/account' => 'close-account'] as $path => $action) {
            $route = $app->delete($path, Controllers\Authentication::class);
            $route->add(RealmAuthentication::class);
            $route->add(new RoutePolicy($realm, $action));
        }

        IdentityContactRoutes::register($app, $realm);
        self::preflight($app);
    }

    /**
     * @param App<T> $app
     *
     * @template T of ContainerInterface|null
     */
    private static function preflight(App $app): void
    {
        $collector = $app->getRouteCollector();
        foreach ($collector->getRoutes() as $route) {
            $app->options($route->getPattern(), Controllers\Preflight::class);
        }
    }

    /**
     * @param App<T> $app
     *
     * @template T of ContainerInterface|null
     */
    private static function administration(App $app): void
    {
        $history = $app->get('/administration/actors/{realm}/{actor-id}', Controllers\ActorHistory::class);
        $history->add(RealmAuthentication::class);
        $history->add(new RoutePolicy(Realm::ADMIN));
        foreach (['/administration/admins/invite' => 'invite-admin', '/administration/admins/approve' => 'approve-admin', '/administration/actors/change' => 'change-actor'] as $path => $action) {
            $route = $app->post($path, Controllers\Administration::class);
            $route->add(RealmAuthentication::class);
            $route->add(new RoutePolicy(Realm::ADMIN, $action));
        }
    }
}
