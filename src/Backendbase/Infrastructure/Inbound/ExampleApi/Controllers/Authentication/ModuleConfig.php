<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\Adapters\Http\Idempotency\Idempotency;
use Backendbase\Infrastructure\Adapters\Http\Identity\ContactRoutePolicy;
use Backendbase\Infrastructure\Adapters\Http\Identity\RealmPolicy;
use Backendbase\Infrastructure\Adapters\Http\Identity\RoutePolicy;
use Override;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;

final class ModuleConfig implements ModuleRoute
{
    public const string ROUTE_KEY = 'auth';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    #[Override]
    public function __invoke(RouteCollectorProxy $routeCollector): void
    {
        $routeCollector->group('', function (RouteCollectorProxy $group): void {
            $group->post('/login', Handlers\PasswordLogin::class)
                ->setName('examplePasswordLogin')
                ->add(new RoutePolicy('login'));

            $group->post('/register', Handlers\RegisterUser::class)
                ->setName('exampleRegisterUser')
                ->add(new RoutePolicy('register'));

            $group->post('/contact-verifications', Handlers\RequestContactVerification::class)
                ->setName('exampleContactRequest')
                ->add(new ContactRoutePolicy(['action' => 'request', 'authenticated' => false]));

            $group->post('/contact-verifications/{contact-info-id}/confirm', Handlers\ConfirmContactVerification::class)
                ->setName('exampleContactConfirm')
                ->add(new ContactRoutePolicy(['action' => 'confirm', 'authenticated' => false]));

            $group->post('/contact-verifications/{contact-info-id}/resend', Handlers\ResendContactVerification::class)
                ->setName('exampleContactResend')
                ->add(new ContactRoutePolicy(['action' => 'resend', 'authenticated' => false]));
        })
            ->add(Idempotency::class)
            ->add(new RealmPolicy(Realm::USER));
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
