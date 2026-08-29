<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Backendbase\Application\Messaging\OutboxRelayService;
use Backendbase\Application\Messaging\QueueMessageFailureService;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ExampleReadRepositoryPort;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryPort;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtAuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareCommandBus;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareQueryBus;
use Backendbase\Infrastructure\Adapters\DomainEvents\ContainerAwareDomainEventPublisher;
use Backendbase\Infrastructure\Adapters\EventManager\ContainerAwareEventManager;
use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineExternalEffectInbox;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineInboxMessageTransaction;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationEventTransaction;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationMessageLogCleaner;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMessageStore;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMonitor;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineQueueMessageFailureStore;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Infrastructure\Adapters\Queue\OutboxMessagePublisher;
use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;
use Backendbase\Infrastructure\Adapters\S3Bucket;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Integrations\BucketService;
use Backendbase\Shared\Integrations\ExternalIntegrationEventRegistry;
use Backendbase\Shared\Integrations\IntegrationMessageLogCleaner;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\OutboxMonitor;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Integrations\OutboxRelay;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Persistence\InboxMessageTransaction;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Persistence\OutboxMessageStore;
use Backendbase\Shared\Persistence\QueueMessageFailureStore;
use Backendbase\Shared\Services\EventManager\EventManager;

final class ProductionPortBindings
{
    /** @return array<class-string, class-string> */
    public static function all(): array
    {
        return [
            AccountAuthenticationRepository::class => DoctrineAccountAuthenticationRepository::class,
            AuthorizationStore::class => JwtAuthorizationStore::class,
            BucketService::class => S3Bucket::class,
            CommandBus::class => ContainerAwareCommandBus::class,
            DomainEventPublisher::class => ContainerAwareDomainEventPublisher::class,
            EventManager::class => ContainerAwareEventManager::class,
            ExampleReadRepositoryPort::class => ExampleReadRepository::class,
            ExampleWriteRepositoryPort::class => ExampleWriteRepository::class,
            ExternalEffectInbox::class => DoctrineExternalEffectInbox::class,
            ExternalIntegrationEventRegistry::class => InMemoryExternalIntegrationEventRegistry::class,
            InboxMessageTransaction::class => DoctrineInboxMessageTransaction::class,
            IntegrationEventTransaction::class => DoctrineIntegrationEventTransaction::class,
            IntegrationMessageLogCleaner::class => DoctrineIntegrationMessageLogCleaner::class,
            MessageConsumer::class => RabbitMQ::class,
            MessagePublisher::class => RabbitMQ::class,
            Notify::class => StackNotifier::class,
            OutboxMessageStore::class => DoctrineOutboxMessageStore::class,
            OutboxMonitor::class => DoctrineOutboxMonitor::class,
            OutboxPublisher::class => OutboxMessagePublisher::class,
            OutboxRelay::class => OutboxRelayService::class,
            QueryBus::class => ContainerAwareQueryBus::class,
            QueueMessageFailurePolicy::class => QueueMessageFailureService::class,
            QueueMessageFailureStore::class => DoctrineQueueMessageFailureStore::class,
            TokenIssuer::class => Jwt::class,
            TokenValidator::class => Jwt::class,
        ];
    }
}
