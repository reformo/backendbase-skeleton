<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventDispatcher;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventMessageProcessor;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Messaging\ReplayWindow;
use Backendbase\Shared\Integrations\Operation\ConsumerRestartRequired;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\InboxMessageTransaction;
use Backendbase\Shared\Services\EventManager\EventManager;
use DateTimeImmutable;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Time\FrozenClock;

final class ExternalIntegrationEventMessageProcessorTest extends TestCase
{
    private const array MESSAGE_CONTRACTS = [
        [
            'eventName' => 'Example_NewExampleAdded_Event',
            'eventVersion' => '1.0',
            'messageFQCN' => EntryAddedMessage::class,
        ],
    ];

    #[Test]
    public function itDispatchesEachMessageOnce(): void
    {
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('getSubscriber')->willReturn([
            'subscriber' => EntryAddedExternalSubscriber::class,
        ]);
        $eventManager->expects(self::once())
            ->method('dispatchExternalEvent')
            ->with(
                'Example_NewExampleAdded_Event',
                self::callback(static function (EntryAddedMessage $message): bool {
                    self::assertSame('example-id', $message->entryId());

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
            new ReplayWindow(new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'))),
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
            'subscriber' => EntryAddedExternalSubscriber::class,
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
            new ReplayWindow(new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'))),
        );

        $processed = $processor->process(new Message(
            'Example_NewExampleAdded',
            ['exampleId' => 'example-id'],
            'message-id',
            '1.0',
            'events',
            occurredAt: '2026-10-14 09:00:00.000000',
        ));

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
            new ReplayWindow(new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'))),
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
                new Message(''),
                new Message('Event'),
                new Message('Event', id: 'message-id'),
                new Message('Event', id: 'message-id', destination: 'events'),
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

    #[DataProvider('restartOutcomes')]
    public function testClosedPersistenceSessionRequiresRestartWithTheRetryPolicyOutcome(QueueMessageHandlingOutcome $outcome): void
    {
        $failure     = new ConsumerRestartRequired(new RuntimeException('Database flush failed.'));
        $transaction = $this->createStub(InboxMessageTransaction::class);
        $transaction->method('processOnce')->willThrowException($failure);
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())->method('transientFailure')
            ->with('events', 'message-id', ConsumerRestartRequired::class)->willReturn($outcome);
        $failurePolicy->expects(self::never())->method('succeeded');
        $processor = $this->processor($transaction, $failurePolicy);
        try {
            $processor->process($this->messageData());
            self::fail('A closed persistence session must stop the consumer.');
        } catch (ConsumerRestartRequired $exception) {
            self::assertSame($outcome, $exception->outcome());
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    /** @return iterable<string, array{QueueMessageHandlingOutcome}> */
    public static function restartOutcomes(): iterable
    {
        yield 'retry' => [QueueMessageHandlingOutcome::RETRY];
        yield 'retry limit' => [QueueMessageHandlingOutcome::REJECT];
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
            new ReplayWindow(new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'))),
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

    private function messageData(): Message
    {
        return new Message(
            'Example_NewExampleAdded',
            [
                'exampleId' => 'example-id',
                'command' => [
                    'exampleId' => 'example-id',
                    'type' => 'system',
                    'typeTargetId' => null,
                    'group' => 'settings',
                    'isActive' => true,
                    'key' => 'key',
                    'value' => 'value',
                    'details' => [],
                ],
            ],
            'message-id',
            '1.0',
            'events',
            occurredAt: '2026-10-14 09:00:00.000000',
        );
    }
}
