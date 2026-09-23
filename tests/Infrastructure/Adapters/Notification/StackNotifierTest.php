<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationBatchFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

final class StackNotifierTest extends TestCase
{
    #[Test]
    public function itRejectsANotificationWithoutAProvider(): void
    {
        $notifier = new StackNotifier(new Logger('notification-test'));

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify($this->emailNotification());
    }

    #[Test]
    public function itPropagatesAProviderFailure(): void
    {
        $provider = $this->createStub(NotificationProvider::class);
        $provider->method('type')->willReturn('email');
        $provider->method('notify')->willThrowException(new RuntimeException('Provider unavailable.'));
        $notifier = new StackNotifier(new Logger('notification-test'));
        $notifier->add($provider);

        $this->expectException(NotificationBatchFailed::class);

        $notifier->notify($this->emailNotification());
    }

    #[Test]
    public function itReturnsTypedProviderResults(): void
    {
        $provider = $this->createMock(NotificationProvider::class);
        $provider->method('type')->willReturn('email');
        $provider->expects(self::once())
            ->method('notify')
            ->willReturn(NotificationResult::delivered('email', 'message-id'));
        $notifier = new StackNotifier(new Logger('notification-test'));
        $notifier->add($provider);

        $result = $notifier->notify($this->emailNotification());

        self::assertTrue($result->has('email'));
        self::assertSame('message-id', $result->messageId('email'));
        self::assertCount(1, $result->deliveries());
    }

    #[Test]
    public function itDispatchesASingleNotificationWithoutAStack(): void
    {
        $provider = $this->createMock(NotificationProvider::class);
        $provider->method('type')->willReturn('sms');
        $provider->expects(self::once())
            ->method('notify')
            ->willReturn(NotificationResult::delivered('sms', 'sms-id'));
        $notifier = new StackNotifier(new Logger('notification-test'));
        $notifier->add($provider);

        $result = $notifier->notify(new SmsNotification('+905551112233', 'Message'));

        self::assertSame('sms-id', $result->messageId('sms'));
    }

    #[Test]
    public function itPreservesResultsForRepeatedTypes(): void
    {
        $provider = $this->createStub(NotificationProvider::class);
        $provider->method('type')->willReturn('sms');
        $provider->method('notify')->willReturnOnConsecutiveCalls(
            NotificationResult::delivered('sms', 'first'),
            NotificationResult::delivered('sms', 'second'),
        );
        $notifier = new StackNotifier(new Logger('notification-test'));
        $notifier->add($provider);
        $stack = new StackNotification();
        $stack->addNotification(new SmsNotification('+905551112233', 'First'));
        $stack->addNotification(new SmsNotification('+905551112233', 'Second'));

        self::assertSame(['first', 'second'], $notifier->notify($stack)->messageIds('sms'));
    }

    #[Test]
    public function itReportsCompletedItemsWhenLaterDeliveryFails(): void
    {
        $provider = $this->createStub(NotificationProvider::class);
        $provider->method('type')->willReturn('sms');
        $provider->method('notify')->willReturnCallback(static function (SmsNotification $notification): NotificationResult {
            if ($notification->message() === 'Second') {
                throw new RuntimeException('Provider unavailable.');
            }

            return NotificationResult::delivered('sms', 'first');
        });
        $notifier = new StackNotifier(new Logger('notification-test'));
        $notifier->add($provider);
        $stack = new StackNotification();
        $stack->addNotification(new SmsNotification('+905551112233', 'First'));
        $stack->addNotification(new SmsNotification('+905551112233', 'Second'));

        try {
            $notifier->notify($stack);
            self::fail('The second delivery must fail.');
        } catch (NotificationBatchFailed $failure) {
            self::assertSame(1, $failure->failedIndex());
            self::assertSame(['first'], $failure->completed()->messageIds('sms'));
            self::assertInstanceOf(RuntimeException::class, $failure->getPrevious());
        }
    }

    private function emailNotification(): StackNotification
    {
        return new StackNotification()->addNotification(new EmailNotification()->setHtmlBody('Message'));
    }
}
