<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use UnexpectedValueException;

final class NotificationBatchResultTest extends TestCase
{
    #[Test]
    public function itRejectsMissingProvidersBeforeAnyDelivery(): void
    {
        $provider = $this->createMock(NotificationProvider::class);
        $provider->method('type')->willReturn('sms');
        $provider->expects(self::never())->method('notify');
        $notifier = new StackNotifier(new NullLogger());
        $notifier->add($provider);
        $stack = new StackNotification();
        $stack->addNotification(new SmsNotification('+905551112233', 'Message'));
        $stack->addNotification(new EmailNotification());

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify($stack);
    }

    #[Test]
    public function itRejectsASecondProviderForTheSameType(): void
    {
        $provider = $this->createStub(NotificationProvider::class);
        $provider->method('type')->willReturn('sms');
        $notifier = new StackNotifier(new NullLogger());
        $notifier->add($provider);

        $this->expectException(UnexpectedValueException::class);

        $notifier->add($provider);
    }

    #[Test]
    public function itRequiresMessageIdsForRepeatedTypesToBeReadAsAList(): void
    {
        $result = NotificationResult::delivered('sms', 'first')
            ->merge(NotificationResult::delivered('email', 'email-id'))
            ->merge(NotificationResult::delivered('sms', 'second'));
        self::assertSame(['first', 'second'], $result->messageIds('sms'));

        $this->expectException(LogicException::class);

        $result->messageId('sms');
    }
}
