<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\Console\Queue;

use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventDispatcher;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventMessageProcessor;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Infrastructure\Adapters\Queue\NotificationMessageProcessor;
use Backendbase\Infrastructure\UseCase\Console\Queue\ContainerAwareQueueConsumer;
use Backendbase\Infrastructure\UseCase\Console\Queue\NotifyReceiver;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Persistence\InboxMessageTransaction;
use Backendbase\Shared\Services\EventManager\EventManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class QueueConsumerCommandsTest extends TestCase
{
    #[Test]
    public function itStartsTheExternalEventQueueConsumer(): void
    {
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects(self::once())
            ->method('consume')
            ->with(new MessageSubscription('events', 20), self::isCallable());
        $tester = new CommandTester(new ContainerAwareQueueConsumer($consumer, $this->eventProcessor()));

        self::assertSame(Command::SUCCESS, $tester->execute(['name' => 'events']));
        self::assertStringContainsString('Queue consumer started', $tester->getDisplay());
    }

    #[Test]
    public function itStartsTheNotificationQueueConsumerWithItsDefaultQueue(): void
    {
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects(self::once())
            ->method('consume')
            ->with(new MessageSubscription('backendbase-queue-email', 20), self::isCallable());
        $processor = new NotificationMessageProcessor(
            $this->createStub(Notify::class),
            $this->createStub(ExternalEffectInbox::class),
            $this->createStub(QueueMessageFailurePolicy::class),
            new NullLogger(),
        );
        $tester    = new CommandTester(new NotifyReceiver($consumer, $processor));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Notifier started consuming', $tester->getDisplay());
    }

    private function eventProcessor(): ExternalIntegrationEventMessageProcessor
    {
        $dispatcher = new ExternalIntegrationEventDispatcher(
            $this->createStub(EventManager::class),
            new InMemoryExternalIntegrationEventRegistry([]),
        );

        return new ExternalIntegrationEventMessageProcessor(
            $dispatcher,
            $this->createStub(InboxMessageTransaction::class),
            $this->createStub(QueueMessageFailurePolicy::class),
            new NullLogger(),
        );
    }
}
