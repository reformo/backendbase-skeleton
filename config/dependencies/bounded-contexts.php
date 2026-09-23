<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\CQRS\RegistryHandlerResolver;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
    $serviceProviders       = glob('src/Backendbase/Domain/*/ServiceProvider.php', GLOB_NOSORT);
    $backendbaseDefinitions = [];
    $handlerDefinitions     = [];
    if ($serviceProviders === false) {
        throw new RuntimeException('Cannot discover domain service providers.');
    }

    foreach ($serviceProviders as $serviceProviderPath) {
        $serviceProvider = str_replace(['src', '.php', '/'], ['', '', '\\'], $serviceProviderPath);
        foreach ($serviceProvider::getDefinitions() as $interface => $implementation) {
            $backendbaseDefinitions[$interface] = autowire($implementation);
        }

        foreach ($serviceProvider::getHandlers() as $message => $handler) {
            if (isset($handlerDefinitions[$message])) {
                throw new UnexpectedValueException('Duplicate CQRS handler mapping for ' . $message);
            }

            $handlerDefinitions[$message] = $handler;
        }
    }

    $backendbaseDefinitions['cqrs-handler-definitions']     = $handlerDefinitions;
    $backendbaseDefinitions[RegistryHandlerResolver::class] = static function (ContainerInterface $container): RegistryHandlerResolver {
        $registeredHandlers = $container->get('cqrs-handler-definitions');
        if (! is_array($registeredHandlers)) {
            throw new UnexpectedValueException('The CQRS handler registry must be an array.');
        }

        $handlerDefinitions = [];
        foreach ($registeredHandlers as $message => $handler) {
            if (! is_string($message) || ! is_string($handler)) {
                throw new UnexpectedValueException('CQRS handler mappings must contain class names.');
            }

            $handlerDefinitions[$message] = $handler;
        }

        return new RegistryHandlerResolver($handlerDefinitions);
    };
    $containerBuilder->addDefinitions($backendbaseDefinitions);
};
