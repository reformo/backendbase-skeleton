<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Infrastructure\Adapters\Queue\NotificationMessageProcessor;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Persistence\ExternalEffectInProgress;
use Backendbase\Shared\Persistence\ExternalEffectOutcomeUnknown;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationMessageProcessorTest extends TestCase
{
    #[Test]
    public function itDeliversEachNotificationOnce(): void
    {
        $notifier = $this->createMock(Notify::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(self::callback(static function (StackNotification $stack): bool {
                $notifications = $stack->notifications();
                self::assertCount(1, $notifications);
                self::assertInstanceOf(EmailNotification::class, $notifications[0]);
                self::assertSame('<p>Hello</p>', $notifications[0]->htmlBody());

                return true;
            }))
            ->willReturn([]);
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())->method('succeeded')->with('email', 'message-id');
        $processor = new NotificationMessageProcessor(
            $notifier,
            $this->executingInboxTransaction(),
            $failurePolicy,
            new Logger('notification-test'),
        );

        $outcome = $processor->process($this->messageData());

        self::assertSame(QueueMessageHandlingOutcome::ACKNOWLEDGE, $outcome);
    }

    #[Test]
    public function itRejectsAnInvalidNotification(): void
    {
        $notifier = $this->createMock(Notify::class);
        $notifier->expects(self::never())->method('notify');
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())
            ->method('permanentFailure')
            ->with('email', 'message-id', self::anything())
            ->willReturn(QueueMessageHandlingOutcome::REJECT);
        $processor = new NotificationMessageProcessor(
            $notifier,
            $this->executingInboxTransaction(),
            $failurePolicy,
            new Logger('notification-test'),
        );

        $outcome = $processor->process([
            'topic' => 'email',
            'messageId' => 'message-id',
            'messageBody' => '{',
        ]);

        self::assertSame(QueueMessageHandlingOutcome::REJECT, $outcome);
    }

    #[Test]
    public function itRejectsAnIndeterminateNotifierFailureWithoutRetry(): void
    {
        $notifier = $this->createStub(Notify::class);
        $notifier->method('notify')->willThrowException(new RuntimeException('Provider unavailable.'));
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())
            ->method('permanentFailure')
            ->with('email', 'message-id', ExternalEffectOutcomeUnknown::class)
            ->willReturn(QueueMessageHandlingOutcome::REJECT);
        $processor = new NotificationMessageProcessor(
            $notifier,
            $this->executingInboxTransaction(),
            $failurePolicy,
            new Logger('notification-test'),
        );

        $outcome = $processor->process($this->messageData());

        self::assertSame(QueueMessageHandlingOutcome::REJECT, $outcome);
    }

    #[Test]
    public function itRetriesWhileAnotherDeliveryAttemptIsActive(): void
    {
        $inbox = $this->createStub(ExternalEffectInbox::class);
        $inbox->method('processOnce')->willThrowException(
            new ExternalEffectInProgress('The external effect is already in progress.'),
        );
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::never())->method('permanentFailure');
        $failurePolicy->expects(self::never())->method('transientFailure');
        $failurePolicy->expects(self::never())->method('succeeded');
        $processor = new NotificationMessageProcessor(
            $this->createStub(Notify::class),
            $inbox,
            $failurePolicy,
            new Logger('notification-test'),
        );

        $outcome = $processor->process($this->messageData());

        self::assertSame(QueueMessageHandlingOutcome::RETRY, $outcome);
    }

    #[Test]
    public function itAcknowledgesAnInboxDuplicateWithoutSendingItAgain(): void
    {
        $notifier = $this->createMock(Notify::class);
        $notifier->expects(self::never())->method('notify');
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())->method('succeeded')->with('email', 'message-id');
        $processor = new NotificationMessageProcessor(
            $notifier,
            $this->createStub(ExternalEffectInbox::class),
            $failurePolicy,
            new Logger('notification-test'),
        );

        $outcome = $processor->process($this->messageData());

        self::assertSame(QueueMessageHandlingOutcome::ACKNOWLEDGE, $outcome);
    }

    #[Test]
    public function itRejectsNotificationsWithMissingMetadata(): void
    {
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::never())->method('permanentFailure');
        $processor = new NotificationMessageProcessor(
            $this->createStub(Notify::class),
            $this->createStub(ExternalEffectInbox::class),
            $failurePolicy,
            new Logger('notification-test'),
        );

        self::assertSame(QueueMessageHandlingOutcome::REJECT, $processor->process([]));
        self::assertSame(
            QueueMessageHandlingOutcome::REJECT,
            $processor->process(['topic' => 'email']),
        );
    }

    #[Test]
    public function itUsesTheTransientPolicyForUnexpectedInboxFailures(): void
    {
        $inbox = $this->createStub(ExternalEffectInbox::class);
        $inbox->method('processOnce')->willThrowException(new RuntimeException('Database unavailable.'));
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::once())
            ->method('transientFailure')
            ->with('email', 'message-id', RuntimeException::class)
            ->willReturn(QueueMessageHandlingOutcome::RETRY);
        $processor = new NotificationMessageProcessor(
            $this->createStub(Notify::class),
            $inbox,
            $failurePolicy,
            new Logger('notification-test'),
        );

        self::assertSame(QueueMessageHandlingOutcome::RETRY, $processor->process($this->messageData()));
    }

    #[Test]
    public function itRejectsMissingAndNonStringNotificationBodies(): void
    {
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::exactly(2))
            ->method('permanentFailure')
            ->willReturn(QueueMessageHandlingOutcome::REJECT);
        $processor = new NotificationMessageProcessor(
            $this->createStub(Notify::class),
            $this->createStub(ExternalEffectInbox::class),
            $failurePolicy,
            new Logger('notification-test'),
        );

        self::assertSame(
            QueueMessageHandlingOutcome::REJECT,
            $processor->process(['topic' => 'email', 'messageId' => 'message-id']),
        );
        self::assertSame(
            QueueMessageHandlingOutcome::REJECT,
            $processor->process([
                'topic' => 'email',
                'messageId' => 'message-id',
                'messageBody' => '[]',
            ]),
        );
    }

    private function executingInboxTransaction(): ExternalEffectInbox
    {
        $transaction = $this->createStub(ExternalEffectInbox::class);
        $transaction->method('processOnce')->willReturnCallback(
            static function (
                string $_consumerName,
                string $_messageId,
                string $_eventName,
                callable $handler,
            ): void {
                try {
                    $handler();
                } catch (RuntimeException $exception) {
                    throw new ExternalEffectOutcomeUnknown(
                        'The external effect started, but its outcome is unknown.',
                        previous: $exception,
                    );
                }
            },
        );

        return $transaction;
    }

    /** @return array<string, mixed> */
    private function messageData(): array
    {
        return [
            'topic' => 'email',
            'messageId' => 'message-id',
            'messageBody' => '"<p>Hello</p>"',
        ];
    }
}
