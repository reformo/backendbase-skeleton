<?php

declare(strict_types=1);

use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use DI\ContainerBuilder;

use function DI\create;

return static function (ContainerBuilder $containerBuilder, array $config): void {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => create(Settings::class)
            ->constructor($config),
    ]);
};
