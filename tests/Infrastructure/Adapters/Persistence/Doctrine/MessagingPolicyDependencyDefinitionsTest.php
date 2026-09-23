<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Application\Messaging\OutboxRelayService;
use Backendbase\Application\Messaging\QueueMessageFailureService;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMessageStore;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineQueueMessageFailureStore;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\OutboxRelay;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\OutboxMessageStore;
use Backendbase\Shared\Persistence\QueueMessageFailureStore;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class MessagingPolicyDependencyDefinitionsTest extends TestCase
{
    #[Test]
    public function itBindsApplicationPoliciesToDoctrineMechanisms(): void
    {
        $containerBuilder = new ContainerBuilder();
        $provider         = require 'config/dependencies/doctrine.php';
        $provider($containerBuilder);
        $timeProvider = require 'config/dependencies/time.php';
        $timeProvider($containerBuilder);
        $containerBuilder->addDefinitions([
            Connection::class => DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            MessagePublisher::class => $this->createStub(MessagePublisher::class),
            LoggerInterface::class => new NullLogger(),
        ]);
        $container = $containerBuilder->build();

        self::assertInstanceOf(OutboxRelayService::class, $container->get(OutboxRelay::class));
        self::assertInstanceOf(
            QueueMessageFailureService::class,
            $container->get(QueueMessageFailurePolicy::class),
        );
        self::assertInstanceOf(
            DoctrineOutboxMessageStore::class,
            $container->get(OutboxMessageStore::class),
        );
        self::assertInstanceOf(
            DoctrineQueueMessageFailureStore::class,
            $container->get(QueueMessageFailureStore::class),
        );
    }
}
