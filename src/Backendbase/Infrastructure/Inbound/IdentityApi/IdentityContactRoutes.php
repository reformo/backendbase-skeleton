<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\ContactRoutePolicy;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\RealmAuthentication;
use Psr\Container\ContainerInterface;
use Slim\App;

final class IdentityContactRoutes
{
    /**
     * @param App<T> $app
     *
     * @template T of ContainerInterface|null
     */
    public static function register(App $app, Realm $realm): void
    {
        self::group($app, $realm, '/auth/contact-verifications', false);
        if ($realm !== Realm::ADMIN) {
            return;
        }

        self::group($app, $realm, '/identity/contact-verifications', true);
    }

    /**
     * @param App<T> $app
     *
     * @template T of ContainerInterface|null
     */
    private static function group(App $app, Realm $realm, string $prefix, bool $authenticated): void
    {
        foreach (['' => 'request', '/{contact-info-id}/confirm' => 'confirm', '/{contact-info-id}/resend' => 'resend'] as $suffix => $action) {
            self::route($app, $realm, $prefix . $suffix, $action, $authenticated);
        }
    }

    /**
     * @param App<T> $app
     *
     * @template T of ContainerInterface|null
     */
    private static function route(App $app, Realm $realm, string $path, string $action, bool $authenticated): void
    {
        $route = $app->post($path, Controllers\ContactVerification::class);
        if ($authenticated) {
            $route->add(RealmAuthentication::class);
        }

        $route->add(new ContactRoutePolicy($realm, ['action' => $action, 'authenticated' => $authenticated]));
    }
}
