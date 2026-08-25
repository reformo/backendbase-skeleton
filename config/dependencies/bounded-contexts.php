<?php

declare(strict_types=1);

use DI\ContainerBuilder;

use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
    $serviceProviders       = glob('src/Backendbase/Domain/*/ServiceProvider.php', GLOB_NOSORT);
    $backendbaseDefinitions = [];
    if ($serviceProviders === false) {
        throw new RuntimeException('Cannot discover domain service providers.');
    }

    foreach ($serviceProviders as $serviceProvider) {
        $serviceProvider = str_replace(['src', '.php', '/'], ['', '', '\\'], $serviceProvider);
        foreach ($serviceProvider::getDefinitions() as $interface => $implementation) {
            $backendbaseDefinitions[$interface] = autowire($implementation);
        }
    }

    $containerBuilder->addDefinitions($backendbaseDefinitions);
};
