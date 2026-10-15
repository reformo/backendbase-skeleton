<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Domain\IdentityAndAccess\Adapters\Notification\ContactNotification;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Adapters\Notification\InboxNotificationDelivery;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineExternalEffectInbox;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Integrations\ReliableNotificationDelivery;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Persistence\MessageRecoveryFixture;

final class OwnershipDeliveryTest extends TestCase
{
    public function testDuplicateOwnershipNotificationsUseTheStableGenerationIdentity(): void
    {
        $fixture    = MessageRecoveryFixture::create();
        $connection = $fixture->connection();
        $connection->executeStatement('ALTER TABLE integration_event_inbox ADD received_at TEXT');
        $provider = $this->createMock(Notify::class);
        $provider->expects(self::once())->method('notify')->with(self::callback(static function (Notification $notification): bool {
            self::assertInstanceOf(EmailNotification::class, $notification);
            self::assertStringContainsString('123456', $notification->htmlBody());
            self::assertSame('Verify contact ownership', $notification->subject());

            return true;
        }))->willReturn(NotificationResult::delivered('email', 'provider-message'));
        $delivery = new InboxNotificationDelivery($provider, new DoctrineExternalEffectInbox($connection, $fixture->clock()));
        $mailer   = new ContactNotification($delivery, IdentityFixture::settings());
        $mailer->send('generation-123', new ContactValue('email', 'user@example.com'), '123456');
        $mailer->send('generation-123', new ContactValue('email', 'user@example.com'), '123456');
        self::assertSame('identity-contact-verification', $connection->fetchOne('SELECT consumer_name FROM integration_event_inbox'));
        self::assertSame('generation-123', $connection->fetchOne('SELECT message_id FROM integration_event_inbox'));
        self::assertNotNull($connection->fetchOne('SELECT processed_at FROM integration_event_inbox'));
    }

    public function testIncompleteProviderAcceptanceLeavesAnUncertainOutcome(): void
    {
        $fixture    = MessageRecoveryFixture::create();
        $connection = $fixture->connection();
        $connection->executeStatement('ALTER TABLE integration_event_inbox ADD received_at TEXT');
        $provider = $this->createMock(Notify::class);
        $provider->expects(self::once())->method('notify')->willReturn(NotificationResult::empty());
        $delivery = new InboxNotificationDelivery($provider, new DoctrineExternalEffectInbox($connection, $fixture->clock()));
        $this->expectException(RuntimeException::class);
        $delivery->send('identity-contact-verification', 'generation-123', new EmailNotification());
    }

    public function testMobileCodesUseTheSmsProviderWithTheGenerationIdentity(): void
    {
        $delivery = $this->createMock(ReliableNotificationDelivery::class);
        $delivery->expects(self::once())->method('send')->with('identity-contact-verification', 'sms-generation', self::callback(static function (Notification $notification): bool {
            self::assertInstanceOf(SmsNotification::class, $notification);
            self::assertSame('+12', $notification->phoneNumber());
            self::assertStringContainsString('123456', $notification->message());

            return true;
        }));
        new ContactNotification($delivery, IdentityFixture::settings())->send('sms-generation', new ContactValue('mobile', '+12'), '123456');
    }
}
