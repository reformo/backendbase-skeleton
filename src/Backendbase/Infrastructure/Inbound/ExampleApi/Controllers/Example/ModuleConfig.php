<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example;

use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\Examples;
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

        $routeCollector->post('/{type-slug}/groups/{example-group}/examples', Handlers\NewExample::class)
            ->setName('addNewExample');
        $routeCollector->patch('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\ChangeExampleDetails::class)
            ->setName('changeExampleDetails');
        $routeCollector->delete('/{type-slug}/groups/{example-group}/examples/{example-key}', Handlers\RemoveExample::class)
            ->setName('removeExample');
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
