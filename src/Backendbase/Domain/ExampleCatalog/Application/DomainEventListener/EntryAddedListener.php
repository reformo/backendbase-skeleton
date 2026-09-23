<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\DomainEventListener;

use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventListener;
use DateTimeInterface;
use Override;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

readonly class EntryAddedListener implements DomainEventListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Override]
    public function handle(DomainEvent $domainEvent): void
    {
        if (! $domainEvent instanceof EntryAdded) {
            throw new UnexpectedValueException($domainEvent::class . ' is not an EntryAdded event.');
        }

        $this->logger->debug('EntryAddedListener', [
            'eventName' => $domainEvent->eventName(),
            'occurredOn' => $domainEvent->occurredOn()->format(DateTimeInterface::ATOM),
            'arguments' => $domainEvent->getEventArguments(),
        ]);
    }
}
