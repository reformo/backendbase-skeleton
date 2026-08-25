<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Domain\ExampleBoundedContext\Application\ExternalIntegrationEventSubscribers\ExampleBoundedContext\NewExampleAddedExternalSubscriber;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1\NewExampleAddedMessage;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventDispatcher;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventMessageProcessor;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Persistence\InboxMessageTransaction;
use Backendbase\Shared\Services\EventManager\EventManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExternalIntegrationEventMessageProcessorTest extends TestCase
{
    private const array MESSAGE_CONTRACTS = [
        [
            'eventName' => 'Example_NewExampleAdded_Event',
            'eventVersion' => '1.0',
            'messageFQCN' => NewExampleAddedMessage::class,
        ],
    ];

    #[Test]
    public function itDispatchesEachMessageOnce(): void
    {
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('getSubscriber')->willReturn([
            'subscriber' => NewExampleAddedExternalSubscriber::class,
        ]);
        $eventManager->expects(self::once())
            ->method('dispatchExternalEvent')
            ->with(
                'Example_NewExampleAdded_Event',
                self::callback(static function (NewExampleAddedMessage $message): bool {
                    self::assertSame('example-id', $message->exampleId());

                    return true;
                }),
            );
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())->method('succeeded')->with('events', 'message-id');
        $processor = new ExternalIntegrationEventMessageProcessor(
            new ExternalIntegrationEventDispatcher(
                $eventManager,
                new InMemoryExternalIntegrationEventRegistry(self::MESSAGE_CONTRACTS),
            ),
            $this->executingInboxTransaction(),
            $failurePolicy,
            new Logger('queue-test'),
        );

        $processed = $processor->process($this->messageData());

        self::assertSame(QueueMessageHandlingOutcome::ACKNOWLEDGE, $processed);
    }

    #[Test]
    public function itReportsFailureWithoutAcknowledgingAnInvalidMessage(): void
    {
        $logHandler = new TestHandler();
        $logger     = new Logger('queue-test');
        $logger->pushHandler($logHandler);
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('getSubscriber')->willReturn([
            'subscriber' => NewExampleAddedExternalSubscriber::class,
        ]);
        $eventManager->expects(self::never())->method('dispatchExternalEvent');
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())
            ->method('permanentFailure')
            ->with('events', 'message-id', self::anything())
            ->willReturn(QueueMessageHandlingOutcome::REJECT);
        $processor = new ExternalIntegrationEventMessageProcessor(
            new ExternalIntegrationEventDispatcher(
                $eventManager,
                new InMemoryExternalIntegrationEventRegistry(self::MESSAGE_CONTRACTS),
            ),
            $this->executingInboxTransaction(),
            $failurePolicy,
            $logger,
        );

        $processed = $processor->process([
            'messageId' => 'message-id',
            'messageBody' => 'Example_NewExampleAdded',
            'eventVersion' => '1.0',
            'topic' => 'events',
            'data' => ['exampleId' => 'example-id'],
        ]);

        self::assertSame(QueueMessageHandlingOutcome::REJECT, $processed);
        self::assertTrue($logHandler->hasErrorRecords());
    }

    #[Test]
    public function itAcknowledgesADuplicateWithoutDispatchingItAgain(): void
    {
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->expects(self::never())->method('dispatchExternalEvent');
        $transaction   = $this->createStub(InboxMessageTransaction::class);
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())->method('succeeded')->with('events', 'message-id');
        $processor = new ExternalIntegrationEventMessageProcessor(
            new ExternalIntegrationEventDispatcher(
                $eventManager,
                new InMemoryExternalIntegrationEventRegistry(self::MESSAGE_CONTRACTS),
            ),
            $transaction,
            $failurePolicy,
            new Logger('queue-test'),
        );

        $processed = $processor->process($this->messageData());

        self::assertSame(QueueMessageHandlingOutcome::ACKNOWLEDGE, $processed);
    }

    #[Test]
    public function itRejectsMessagesWithMissingMetadata(): void
    {
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())
            ->method('permanentFailure')
            ->willReturn(QueueMessageHandlingOutcome::REJECT);
        $processor = $this->processor($this->executingInboxTransaction(), $failurePolicy);

        foreach (
            [
                [],
                ['messageBody' => 'Event'],
                ['messageBody' => 'Event', 'messageId' => 'message-id'],
                ['messageBody' => 'Event', 'messageId' => 'message-id', 'topic' => 'events'],
            ] as $message
        ) {
            self::assertSame(QueueMessageHandlingOutcome::REJECT, $processor->process($message));
        }
    }

    #[Test]
    public function itUsesTheTransientFailurePolicyForUnexpectedFailures(): void
    {
        $transaction = $this->createStub(InboxMessageTransaction::class);
        $transaction->method('processOnce')->willThrowException(new RuntimeException('Database unavailable.'));
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())
            ->method('transientFailure')
            ->with('events', 'message-id', RuntimeException::class)
            ->willReturn(QueueMessageHandlingOutcome::RETRY);
        $processor = $this->processor($transaction, $failurePolicy);

        self::assertSame(QueueMessageHandlingOutcome::RETRY, $processor->process($this->messageData()));
    }

    private function processor(
        InboxMessageTransaction $transaction,
        QueueMessageFailurePolicy $failurePolicy,
    ): ExternalIntegrationEventMessageProcessor {
        return new ExternalIntegrationEventMessageProcessor(
            new ExternalIntegrationEventDispatcher(
                $this->createStub(EventManager::class),
                new InMemoryExternalIntegrationEventRegistry(self::MESSAGE_CONTRACTS),
            ),
            $transaction,
            $failurePolicy,
            new Logger('queue-test'),
        );
    }

    private function executingInboxTransaction(): InboxMessageTransaction
    {
        $transaction = $this->createStub(InboxMessageTransaction::class);
        $transaction->method('processOnce')->willReturnCallback(
            static function (
                string $_consumerName,
                string $_messageId,
                string $_eventName,
                callable $handler,
            ): void {
                $handler();
            },
        );

        return $transaction;
    }

    /** @return array<string, mixed> */
    private function messageData(): array
    {
        return [
            'messageId' => 'message-id',
            'messageBody' => 'Example_NewExampleAdded',
            'eventVersion' => '1.0',
            'topic' => 'events',
            'data' => [
                'exampleId' => 'example-id',
                'type' => 'system',
                'typeTargetId' => null,
                'group' => 'settings',
                'isActive' => true,
                'key' => 'key',
                'value' => 'value',
                'details' => [],
            ],
        ];
    }
}
