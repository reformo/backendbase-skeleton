<?php

declare(strict_types=1);

namespace Tests\Shared\Primitives\Notification;

use Backendbase\Shared\Primitives\Notification\PushNotification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PushNotificationValidationTest extends TestCase
{
    #[Test]
    public function itRejectsTwoTargets(): void
    {
        $notification = new PushNotification();
        $notification->setTopic('updates')->setDeviceToken('device-token');

        $this->expectException(InvalidArgumentException::class);

        $notification->target();
    }

    #[Test]
    public function itRejectsAnEmptyTarget(): void
    {
        $notification = new PushNotification();
        $notification->setTopic(' ');

        $this->expectException(InvalidArgumentException::class);

        $notification->target();
    }

    #[Test]
    public function itRejectsAnEmptyDeviceToken(): void
    {
        $notification = new PushNotification();
        $notification->setDeviceToken(' ');

        $this->expectException(InvalidArgumentException::class);

        $notification->target();
    }

    #[Test]
    public function itRejectsNestedCustomData(): void
    {
        $notification = new PushNotification();

        $this->expectException(InvalidArgumentException::class);

        $notification->setData(['nested' => ['invalid']]);
    }

    #[Test]
    public function itRejectsAnEmptyImage(): void
    {
        $notification = new PushNotification();

        $this->expectException(InvalidArgumentException::class);

        $notification->setNotificationImage(' ');
    }

    #[Test]
    public function itReadsTheImageFromItsDedicatedField(): void
    {
        $notification = new PushNotification();
        $notification->setData(['notificationImage' => 'older-image.jpg']);
        $notification->setNotificationImage('new-image.jpg');

        self::assertSame('new-image.jpg', $notification->notificationImage());
        self::assertSame(['notificationImage' => 'older-image.jpg'], $notification->data());
    }

    #[Test]
    public function itKeepsTheLegacyCustomDataImageWhenNoDedicatedImageExists(): void
    {
        $notification = new PushNotification();
        $notification->setData(['notificationImage' => 'older-image.jpg']);

        self::assertSame('older-image.jpg', $notification->notificationImage());
    }
}
