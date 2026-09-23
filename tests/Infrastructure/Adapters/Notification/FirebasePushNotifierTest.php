<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\FirebasePushNotifier;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\PushNotification;
use Backendbase\Shared\Primitives\Notification\PushPlatformOptions;
use InvalidArgumentException;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\CloudMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class FirebasePushNotifierTest extends TestCase
{
    #[Test]
    public function itSendsACompleteFirebasePushNotification(): void
    {
        $notification = new PushNotification();
        $notification
            ->setTitle('Title')
            ->setBody('Body')
            ->setTopic('updates')
            ->setData(['count' => 2])
            ->setNotificationImage('images/push.jpg')
            ->setPlatformOptions(new PushPlatformOptions(
                ['priority' => 'high'],
                ['headers' => ['apns-push-type' => 'background']],
            ));

        $client = $this->createMock(Messaging::class);
        $client->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (CloudMessage $message): bool {
                $payload = $message->jsonSerialize();
                self::assertSame('Title', $payload['notification']['title'] ?? null);
                self::assertSame('Body', $payload['notification']['body'] ?? null);
                self::assertSame('updates', $payload['topic'] ?? null);
                self::assertSame('2', $payload['data']['count'] ?? null);
                self::assertSame(
                    'https://cdn.example.com/images/push.jpg',
                    $payload['android']['notification']['image'] ?? null,
                );
                self::assertSame('high', $payload['android']['priority'] ?? null);
                self::assertSame('background', $payload['apns']['headers']['apns-push-type'] ?? null);

                return true;
            }))
            ->willReturn(['name' => 'message-id']);
        $notifier = new FirebasePushNotifier($client, 'https://cdn.example.com/');

        self::assertSame('message-id', $notifier->notify($notification)->messageId('push'));
        self::assertSame('push', $notifier->type());
    }

    #[Test]
    public function itTargetsADeviceToken(): void
    {
        $notification = new PushNotification();
        $notification->setTitle('Title')->setBody('Body')->setDeviceToken('device-token');
        $client = $this->createMock(Messaging::class);
        $client->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (CloudMessage $message): bool {
                self::assertSame('device-token', $message->jsonSerialize()['token'] ?? null);

                return true;
            }))
            ->willReturn(['name' => 'message-id']);
        $notifier = new FirebasePushNotifier($client);

        self::assertSame('message-id', $notifier->notify($notification)->messageId('push'));
    }

    #[Test]
    public function itRejectsTwoPushTargetsBeforeSending(): void
    {
        $client = $this->createMock(Messaging::class);
        $client->expects(self::never())->method('send');
        $notification = new PushNotification();
        $notification->setTopic('updates')->setDeviceToken('device-token');

        $this->expectException(InvalidArgumentException::class);

        new FirebasePushNotifier($client)->notify($notification);
    }

    #[Test]
    public function itRejectsAnUnsupportedModel(): void
    {
        $client = $this->createStub(Messaging::class);

        $this->expectException(UnexpectedValueException::class);

        new FirebasePushNotifier($client)->notify(new EmailNotification());
    }

    #[Test]
    public function itTranslatesAFirebaseFailure(): void
    {
        $client = $this->createStub(Messaging::class);
        $client->method('send')->willThrowException(new ServerUnavailable('Firebase unavailable.'));
        $notification = new PushNotification();
        $notification->setTopic('updates')->setBody('Message');

        $this->expectException(NotificationProviderFailed::class);

        new FirebasePushNotifier($client)->notify($notification);
    }

    #[Test]
    public function itRejectsARelativeImageWithoutACdnBaseUrl(): void
    {
        $client = $this->createMock(Messaging::class);
        $client->expects(self::never())->method('send');
        $notification = new PushNotification();
        $notification->setTopic('updates')->setNotificationImage('images/push.jpg');

        $this->expectException(InvalidArgumentException::class);

        new FirebasePushNotifier($client)->notify($notification);
    }
}
