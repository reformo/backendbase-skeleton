<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
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
        $provider = $this->createStub(Notify::class);
        $provider->method('type')->willReturn('email');
        $provider->method('notify')->willThrowException(new RuntimeException('Provider unavailable.'));
        $notifier = new StackNotifier(new Logger('notification-test'));
        $notifier->add($provider);

        $this->expectException(RuntimeException::class);

        $notifier->notify($this->emailNotification());
    }

    #[Test]
    public function itReturnsTypedProviderResults(): void
    {
        $provider = $this->createMock(Notify::class);
        $provider->method('type')->willReturn('email');
        $provider->expects(self::once())
            ->method('notify')
            ->willReturn(NotificationResult::delivered('email', 'message-id'));
        $notifier = new StackNotifier(new Logger('notification-test'));
        $notifier->add($provider);

        $result = $notifier->notify($this->emailNotification());

        self::assertTrue($result->has('email'));
        self::assertSame('message-id', $result->messageId('email'));
        self::assertSame('stack', $notifier->type());
    }

    private function emailNotification(): StackNotification
    {
        return new StackNotification()->addNotification(new EmailNotification()->setHtmlBody('Message'));
    }
}
