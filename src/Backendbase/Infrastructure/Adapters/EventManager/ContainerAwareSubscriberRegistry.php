<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\EventManager;

use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Backendbase\Utility\Resolver;
use Psr\Container\ContainerInterface;
use ReflectionClass;

use function fnmatch;
use function hash;
use function str_contains;

final class ContainerAwareSubscriberRegistry
{
    /**
     * @var array{
     *     exact: array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>>,
     *     wildcard: array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>>
     * }
     */
    private array $subscribers = ['exact' => [], 'wildcard' => []];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN */
    public function resolve(string $subscriberFQCN): object
    {
        $arguments                   = [];
        $handlerConstructorArguments = Resolver::getParameterHints($subscriberFQCN, '__construct');
        foreach ($handlerConstructorArguments as $argumentName => $argumentType) {
            $arguments[] = $this->argument($argumentName, $argumentType);
        }

        return (new ReflectionClass($subscriberFQCN))->newInstanceArgs($arguments);
    }

    /** @return array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>> */
    public function forEvent(string $event): array
    {
        $subscribers = $this->subscribers['exact'][$event] ?? [];
        foreach ($this->subscribers['wildcard'] as $eventPattern => $wildcardSubscribers) {
            $subscribers += fnmatch($eventPattern, $event) ? $wildcardSubscribers : [];
        }

        return $subscribers;
    }

    /** @return array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>> */
    public function all(): array
    {
        return $this->subscribers['exact'] + $this->subscribers['wildcard'];
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    public function add(string|array $events, string $subscriberFQCN): void
    {
        $hash = hash('sha256', $subscriberFQCN);
        foreach ((array) $events as $event) {
            $key                                    = str_contains($event, '*') ? 'wildcard' : 'exact';
            $this->subscribers[$key][$event][$hash] = $subscriberFQCN;
        }
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    public function remove(string|array $events, string $subscriberFQCN): void
    {
        $hash = hash('sha256', $subscriberFQCN);
        foreach ((array) $events as $event) {
            unset($this->subscribers['exact'][$event][$hash], $this->subscribers['wildcard'][$event][$hash]);
        }
    }

    private function argument(string $argumentName, string $argumentType): mixed
    {
        if ($this->container->has($argumentType)) {
            return $this->container->get($argumentType);
        }

        return $this->container->get($argumentName);
    }
}
