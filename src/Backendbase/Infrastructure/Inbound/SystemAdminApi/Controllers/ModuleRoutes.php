<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers;

use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Administration\ModuleConfig as AdministrationModuleConfig;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Authentication\ModuleConfig as AuthenticationModuleConfig;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Identity\ModuleConfig as IdentityModuleConfig;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\System\ModuleConfig as SystemModuleConfig;

final class ModuleRoutes
{
    /** @var array<string, class-string<ModuleRoute>> */
    private array $modules = [];

    public function __construct()
    {
        $this->addModule(AuthenticationModuleConfig::ROUTE_KEY, AuthenticationModuleConfig::class);
        $this->addModule(IdentityModuleConfig::ROUTE_KEY, IdentityModuleConfig::class);
        $this->addModule(SystemModuleConfig::ROUTE_KEY, SystemModuleConfig::class);
        $this->addModule(AdministrationModuleConfig::ROUTE_KEY, AdministrationModuleConfig::class);
    }

    /** @param class-string<ModuleRoute> $moduleClass */
    private function addModule(string $routeKey, string $moduleClass): void
    {
        $this->modules[$routeKey] = $moduleClass;
    }

    /** @return array<string, class-string<ModuleRoute>> */
    public function getModules(): array
    {
        return $this->modules;
    }
}
