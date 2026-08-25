<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\FirebasePushNotifier;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FirebasePushNotifierTest extends TestCase
{
    #[Test]
    public function itSendsACompleteFirebasePushNotification(): void
    {
        $notification = new ConfigurablePushNotification();
        $notification
            ->setTitle('Title')
            ->setBody('Body')
            ->setTopic('updates')
            ->setData(['count' => 2, 'notificationImage' => 'images/push.jpg']);

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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')->with('motification message', self::isArray());
        $notifier = new FirebasePushNotifier($client, $logger, 'https://cdn.example.com/');

        self::assertSame(['name' => 'message-id'], $notifier->notify($notification));
        self::assertSame('push', $notifier->type());
        self::assertSame($client, $notifier->getClient());
    }

    #[Test]
    public function itTargetsADeviceToken(): void
    {
        $notification = new TokenPushNotification();
        $notification->setTitle('Title')->setBody('Body')->setDeviceToken('device-token')->setData([]);
        $client = $this->createMock(Messaging::class);
        $client->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (CloudMessage $message): bool {
                self::assertSame('device-token', $message->jsonSerialize()['token'] ?? null);

                return true;
            }))
            ->willReturn(['name' => 'message-id']);
        $notifier = new FirebasePushNotifier(
            $client,
            $this->createStub(LoggerInterface::class),
        );

        self::assertSame(['name' => 'message-id'], $notifier->notify($notification));
    }
}
