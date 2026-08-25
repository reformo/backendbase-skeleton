<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services\EventManager;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Backendbase\Utility\Resolver;
use Override;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use UnexpectedValueException;

use function fnmatch;
use function hash;
use function str_contains;

class ContainerAwareEventManager implements EventManager
{
    /** @var array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>> */
    private array $subscribers = [];
    /** @var array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>> */
    private array $wildcardSubscribers = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function dispatchEvent(IntegrationEvent $event): void
    {
        $this->logger->debug('ContainerAwareEventManager-1', ['event' => $event->eventName()]);
        $subscribers = $this->getSubscriber($event->eventName());
        if ($subscribers === []) {
            return;
        }

        $this->logger->debug('ContainerAwareEventManager-2', ['event' => $event]);
        foreach ($subscribers as $subscriberFQCN) {
            $this->logger->debug('ContainerAwareEventManager-3', ['subscriber' => $subscriberFQCN]);
            $subscriber = $this->autowireSubscriber($subscriberFQCN);
            if (! $subscriber instanceof IntegrationEventSubscriber) {
                throw new UnexpectedValueException($subscriberFQCN . ' is not an integration event subscriber.');
            }

            $subscriber->handle($event);
        }
    }

    public function dispatchExternalEvent(string $eventName, EventMessage $message): void
    {
        $this->logger->debug('ContainerAwareEventManager-1', ['message' => $message->toArray()]);
        $subscribers = $this->getSubscriber($eventName);
        if ($subscribers === []) {
            return;
        }

        foreach ($subscribers as $subscriberFQCN) {
            $this->logger->debug('ContainerAwareEventManager-3', ['subscriber' => $subscriberFQCN]);
            $subscriber = $this->autowireSubscriber($subscriberFQCN);
            if (! $subscriber instanceof ExternalIntegrationEventSubscriber) {
                throw new UnexpectedValueException($subscriberFQCN . ' is not an external integration event subscriber.');
            }

            $subscriber->handle($message);
        }
    }

    /** @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN */
    private function autowireSubscriber(string $subscriberFQCN): IntegrationEventSubscriber|ExternalIntegrationEventSubscriber
    {
        $arguments                   = [];
        $handlerConstructorArguments = Resolver::getParameterHints($subscriberFQCN, '__construct');
        foreach ($handlerConstructorArguments as $argumentName => $argumentType) {
            $arguments[] = $this->getArgument($argumentName, $argumentType);
        }

        return (new ReflectionClass($subscriberFQCN))->newInstanceArgs($arguments);
    }

    private function getArgument(string $argumentName, string $argumentType): mixed
    {
        return $this->container->has($argumentType) ? $this->container->get($argumentType) : $this->container->get($argumentName);
    }

    /** @return array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>> */
    #[Override]
    public function getSubscriber(string $event): array
    {
        $subscribers = $this->subscribers[$event] ?? [];
        foreach ($this->wildcardSubscribers as $eventPattern => $wildcardSubscribers) {
            $subscribers += fnmatch($eventPattern, $event) ? $wildcardSubscribers : [];
        }

        $this->logger->debug('ContainerAwareEventManager-5', ['subscribers' => $subscribers]);

        return $subscribers;
    }

    /** @return array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>> */
    #[Override]
    public function getAllSubscribers(): array
    {
        return $this->subscribers + $this->wildcardSubscribers;
    }

    #[Override]
    public function hasSubscriber(string $event): bool
    {
        return $this->getSubscriber($event) !== [];
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    #[Override]
    public function addEventSubscriber(string|array $events, string $subscriberFQCN): void
    {
        $hash = hash('sha256', $subscriberFQCN);
        foreach ((array) $events as $event) {
            if (str_contains((string) $event, '*')) {
                $this->wildcardSubscribers[$event][$hash] = $subscriberFQCN;
            } else {
                $this->subscribers[$event][$hash] = $subscriberFQCN;
            }
        }
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    #[Override]
    public function removeEventSubscriber(string|array $events, string $subscriberFQCN): void
    {
        $hash = hash('sha256', $subscriberFQCN);
        foreach ((array) $events as $event) {
            unset($this->subscribers[$event][$hash], $this->wildcardSubscribers[$event][$hash]);
        }
    }
}
