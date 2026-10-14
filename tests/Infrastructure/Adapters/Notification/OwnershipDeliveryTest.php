<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Domain\IdentityAndAccess\Adapters\Notification\OwnershipMailer;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Adapters\Notification\InboxNotificationDelivery;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineExternalEffectInbox;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\Notification;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Persistence\MessageRecoveryFixture;

final class OwnershipDeliveryTest extends TestCase
{
    public function testDuplicateOwnershipNotificationsUseTheStableChallengeIdentity(): void
    {
        $fixture    = MessageRecoveryFixture::create();
        $connection = $fixture->connection();
        $connection->executeStatement('ALTER TABLE integration_event_inbox ADD received_at TEXT');
        $provider = $this->createMock(Notify::class);
        $provider->expects(self::once())->method('notify')->with(self::callback(static function (Notification $notification): bool {
            self::assertInstanceOf(EmailNotification::class, $notification);
            self::assertStringContainsString('123456', $notification->htmlBody());
            self::assertSame('Verify account email ownership', $notification->subject());

            return true;
        }))->willReturn(NotificationResult::delivered('email', 'provider-message'));
        $delivery = new InboxNotificationDelivery($provider, new DoctrineExternalEffectInbox($connection, $fixture->clock()));
        $mailer   = new OwnershipMailer($delivery, IdentityFixture::settings());
        $mailer->send('challenge-123', new AccountEmail('user@example.com'), '123456');
        $mailer->send('challenge-123', new AccountEmail('user@example.com'), '123456');
        self::assertSame('identity-contact-ownership', $connection->fetchOne('SELECT consumer_name FROM integration_event_inbox'));
        self::assertSame('challenge-123', $connection->fetchOne('SELECT message_id FROM integration_event_inbox'));
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
        $delivery->send('identity-contact-ownership', 'challenge-123', new EmailNotification());
    }
}
