<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example;

use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\Examples;
use Backendbase\Infrastructure\Inbound\ExampleApi\Middleware\AuthorizationMiddleware;
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
        $routeCollector->get('/{type-slug}/groups', Handlers\ExampleGroups::class)
            ->setName('getExampleGroupsByType');
        $routeCollector->get('/{type-slug}/groups/{example-group}/examples', Examples::class)
            ->setName('getExamplesByGroup');
        $routeCollector->get('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\ExampleDetails::class)
            ->setName('getExampleByCriteria');

        /**
         * The following routes need to be authenticated, therefore, they are grouped and Authorization middleware added
         * leaving the pattern parameter of group function empty is crucial to carry the base url of the module to sub routes
         * */
        /** @param RouteCollectorProxy<ContainerInterface> $group */
        $routeCollector->group('', function (RouteCollectorProxy $group): void {
            $group->post('/{type-slug}/groups/{example-group}/examples', Handlers\NewExample::class)
                ->setName('addNewExample');
            $group->patch('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\ChangeExampleDetails::class)
                ->setName('changeExampleDetails');
            $group->delete('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\RemoveExample::class)
                ->setName('removeExample');
        })->add(AuthorizationMiddleware::class);
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
