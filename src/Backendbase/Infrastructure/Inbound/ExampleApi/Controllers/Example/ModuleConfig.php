<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\Adapters\Http\Identity\RealmAuthentication;
use Backendbase\Infrastructure\Adapters\Http\Identity\RealmPolicy;
use Override;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;

class ModuleConfig implements ModuleRoute
{
    /**
     * The route key is used to group the routes under a specific module and route definition.
     * This route key comes after the root of the domain. In this context: https://example-api.backendbaseapp.com/example-types
     */
    public const string ROUTE_KEY = 'example-types';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    #[Override]
    public function __invoke(RouteCollectorProxy $routeCollector): void
    {
        $routeCollector->get('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\ExampleDetails::class)
            ->setName('getExampleByCriteria');

        $routeCollector->group('', function (RouteCollectorProxy $group): void {
            $group->get('/{type-slug}/groups', Handlers\ExampleGroups::class)
                ->setName('getExampleGroupsByType');
            $group->get('/{type-slug}/groups/{example-group}/examples', Handlers\Examples::class)
                ->setName('getExamplesByGroup');

            $group->post('/{type-slug}/groups/{example-group}/examples', Handlers\NewExample::class)
                ->setName('addNewExample');
            $group->patch('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\ChangeExampleDetails::class)
                ->setName('changeExampleDetails');
            $group->delete('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\RemoveExample::class)
                ->setName('removeExample');
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
