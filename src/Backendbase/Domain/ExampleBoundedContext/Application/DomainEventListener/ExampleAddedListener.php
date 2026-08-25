<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\DomainEventListener;

use Backendbase\Domain\ExampleBoundedContext\Contracts\DomainEvents\ExampleAdded;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventListener;
use DateTimeInterface;
use Override;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

readonly class ExampleAddedListener implements DomainEventListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Override]
    public function handle(DomainEvent $domainEvent): void
    {
        if (! $domainEvent instanceof ExampleAdded) {
            throw new UnexpectedValueException($domainEvent::class . ' is not an ExampleAdded event.');
        }

        $this->logger->debug('ExampleAddedListener', [
            'eventName' => $domainEvent->eventName(),
            'occurredOn' => $domainEvent->occurredOn()->format(DateTimeInterface::ATOM),
            'arguments' => $domainEvent->getEventArguments(),
        ]);
    }
}
