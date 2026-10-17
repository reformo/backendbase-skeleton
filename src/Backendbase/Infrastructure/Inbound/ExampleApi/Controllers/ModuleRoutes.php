<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers;

/**
 * Submodules' routes must be introduced to api here
 * When a new module is added, then its ModuleConfig class must be imported and added here
 * */

use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Authentication\ModuleConfig as AuthenticationModuleConfig;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ModuleConfig as ExampleModuleConfig;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Identity\ModuleConfig as IdentityModuleConfig;

class ModuleRoutes
{
    /** @var array<string, class-string> */
    private array $modules = [];

    public function __construct()
    {
        $this->addModule(AuthenticationModuleConfig::ROUTE_KEY, AuthenticationModuleConfig::class);
        $this->addModule(IdentityModuleConfig::ROUTE_KEY, IdentityModuleConfig::class);
        $this->addModule(ExampleModuleConfig::ROUTE_KEY, ExampleModuleConfig::class);
    }

    /** @param class-string $moduleClass */
    private function addModule(string $routeKey, string $moduleClass): void
    {
        $this->modules[$routeKey] = $moduleClass;
    }

    /** @return array<string, class-string> */
    public function getModules(): array
    {
        return $this->modules;
    }
}
