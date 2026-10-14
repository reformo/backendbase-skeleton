<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Services\EventManager\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final readonly class DoctrineIntegrationEventTransaction implements IntegrationEventTransaction
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventManager $eventManager,
    ) {
    }

    /** @param callable(): IntegrationEvent $transactionalWork */
    public function execute(callable $transactionalWork): void
    {
        $eventManager  = $this->eventManager;
        $entityManager = $this->entityManager;
        $connection    = $entityManager->getConnection();
        try {
            $connection->transactional(static function () use ($transactionalWork, $eventManager, $entityManager): void {
                $event = $transactionalWork();
                $eventManager->dispatchEvent($event);
                $entityManager->flush();
            });
        } catch (Throwable $exception) {
            $entityManager->clear();

            throw $exception;
        }
    }
}
