<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers;

/**
 * Submodules' routes must be introduced to api here
 * When a new module is added, then its ModuleConfig class must be imported and added here
 * */

use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\ModuleConfig as AccountModuleConfig;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ModuleConfig as ExampleModuleConfig;

class ModuleRoutes
{
    /** @var array<string, class-string> */
    private array $modules = [];

    public function __construct()
    {
        $this->addModule(AccountModuleConfig::ROUTE_KEY, AccountModuleConfig::class);
        $this->addModule(ExampleModuleConfig::ROUTE_KEY, ExampleModuleConfig::class);
    }

    /** @param class-string $moduleFQCN */
    private function addModule(string $routeKey, string $moduleFQCN): void
    {
        $this->modules[$routeKey] = $moduleFQCN;
    }

    /** @return array<string, class-string> */
    public function getModules(): array
    {
        return $this->modules;
    }
}
