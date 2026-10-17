<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\DomainEvents;

use Backendbase\Shared\Domain\Attributes\DomainEventListener as DomainEventListenerAttribute;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventListener;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use UnexpectedValueException;

readonly class ContainerAwareDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(private ContainerInterface $container)
    {
    }

    #[Override]
    public function publish(DomainEvent $domainEvent): void
    {
        $domainEventClass = $domainEvent::class;
        $reflection       = new ReflectionClass($domainEventClass);
        $attributes       = $reflection->getAttributes(DomainEventListenerAttribute::class);

        if ($attributes === []) {
            throw new UnexpectedValueException($domainEventClass . ' has no domain event listener.');
        }

        $listenerAttribute = $attributes[0]->newInstance();
        $handlerClass      = $listenerAttribute->handlerName;

        $handler = $this->container->get($handlerClass);
        if (! $handler instanceof DomainEventListener) {
            throw new UnexpectedValueException($handlerClass . ' is not a domain event listener.');
        }

        $handler->handle($domainEvent);
    }
}
