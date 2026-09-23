<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareCommandBus;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareQueryBus;
use Backendbase\Infrastructure\Adapters\DomainEvents\ContainerAwareDomainEventPublisher;
use Backendbase\Infrastructure\Adapters\EventManager\ContainerAwareEventManager;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\HandlerResolver;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Integrations\ExternalIntegrationEventRegistry;
use Backendbase\Shared\Persistence\Outbox\IntegrationEventOutbox;
use Backendbase\Shared\Services\EventManager\EventManager;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        EventManager::class => static function (ContainerInterface $container) {
            $logger           = $container->get(LoggerInterface::class);
            $outbox           = $container->get(IntegrationEventOutbox::class);
            $eventManager     = new ContainerAwareEventManager($container, $logger, $outbox);
            $serviceProviders = glob('src/Backendbase/Domain/*/ServiceProvider.php', GLOB_NOSORT);
            if ($serviceProviders === false) {
                throw new RuntimeException('Cannot discover domain service providers.');
            }

            foreach ($serviceProviders as $serviceProvider) {
                $serviceProvider = str_replace(['src', '.php', '/'], ['', '', '\\'], $serviceProvider);
                foreach ($serviceProvider::getIntegrationEventSubscribers() as $eventSubscriber) {
                    $eventManager->addEventSubscriber($eventSubscriber['events'], $eventSubscriber['subscriberFQCN']);
                }
            }

            return $eventManager;
        },
        ExternalIntegrationEventRegistry::class => static function () {
            $definitions      = [];
            $serviceProviders = glob('src/Backendbase/Domain/*/ServiceProvider.php', GLOB_NOSORT);
            if ($serviceProviders === false) {
                throw new RuntimeException('Cannot discover domain service providers.');
            }

            foreach ($serviceProviders as $serviceProvider) {
                $serviceProvider = str_replace(['src', '.php', '/'], ['', '', '\\'], $serviceProvider);
                foreach ($serviceProvider::getIntegrationEventSubscribers() as $eventSubscriber) {
                    if (! isset($eventSubscriber['messageFQCN'], $eventSubscriber['eventVersion'])) {
                        continue;
                    }

                    foreach ($eventSubscriber['events'] as $eventName) {
                        $definitions[] = [
                            'eventName' => $eventName,
                            'eventVersion' => $eventSubscriber['eventVersion'],
                            'messageFQCN' => $eventSubscriber['messageFQCN'],
                        ];
                    }
                }
            }

            return new InMemoryExternalIntegrationEventRegistry($definitions);
        },
        DomainEventPublisher::class => static fn (ContainerInterface $container) => new ContainerAwareDomainEventPublisher($container),
        CommandBus::class => static fn (ContainerInterface $container) => new ContainerAwareCommandBus($container, $container->get(HandlerResolver::class)),
        QueryBus::class => static fn (ContainerInterface $container) => new ContainerAwareQueryBus($container, $container->get(HandlerResolver::class)),
        'Backendbase\Domain\*\Application\Command\Handlers\*Handler' => autowire('Backendbase\Domain\*\Application\Command\Handlers\*Handler'),
        'Backendbase\Domain\*\Application\Query\Handlers\*Handler' => autowire('Backendbase\Domain\*\Application\Query\Handlers\*Handler'),
        'Backendbase\Domain\*\Application\CommandHandlers\*Handler' => autowire('Backendbase\Domain\*\Application\CommandHandlers\*Handler'),
        'Backendbase\Domain\*\Application\QueryHandlers\*Handler' => autowire('Backendbase\Domain\*\Application\QueryHandlers\*Handler'),
        'Backendbase\Domain\*\Application\DomainEventListener\*Listener' => autowire('Backendbase\Domain\*\Application\DomainEventListener\*Listener'),
        'Backendbase\Domain\*\Application\IntegrationEventSubscriber\*Subscriber' => autowire('Backendbase\Domain\*\Application\IntegrationEventSubscriber\*Subscriber'),
        'Backendbase\Domain\*\Adapters\Persistence\Doctrine\ReadModels\*' => autowire('Backendbase\Domain\*\Adapters\Persistence\Doctrine\ReadModels\*'),
    ]);
};
