<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application;

use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;

final readonly class EntryEventTransaction
{
    public function __construct(private IntegrationEventTransaction $transaction, private DomainEventPublisher $publisher)
    {
    }

    /** @param callable(): IntegrationEvent $work */
    public function execute(callable $work, DomainEvent|null $domainEvent = null): void
    {
        $transaction = $this->transaction;
        $publisher   = $this->publisher;
        $transaction->execute(static function () use ($work, $domainEvent, $publisher): IntegrationEvent {
            $event = $work();
            if ($domainEvent !== null) {
                $publisher->publish($domainEvent);
            }

            return $event;
        });
    }
}
