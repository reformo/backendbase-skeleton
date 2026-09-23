<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Aws\Command;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Backendbase\Infrastructure\Adapters\Notification\EmailMessageFactory;
use Backendbase\Infrastructure\Adapters\Notification\SesEmailNotifier;
use Backendbase\Infrastructure\Adapters\Notification\SmtpEmailNotifier;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\Attachment;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use UnexpectedValueException;

final class EmailNotifierTest extends TestCase
{
    #[Test]
    public function itSendsAnEmailThroughSes(): void
    {
        $handler  = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame('SendEmail', $command->getName());
                self::assertSame('from@example.com', $command['FromEmailAddress']);
                $raw = $command['Content']['Raw']['Data'];
                self::assertStringContainsString('to@example.com', $raw);
                self::assertStringContainsString('Subject: Subject', $raw);
                self::assertStringContainsString('file.txt', $raw);

                return new Result(['MessageId' => 'ses-message-id']);
            },
        ]);
        $client   = new SesV2Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
        $notifier = new SesEmailNotifier($client, new EmailMessageFactory());

        self::assertSame('ses-message-id', $notifier->notify(self::email())->messageId('email'));
    }

    #[Test]
    public function itSendsAnEmailThroughSmtp(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $message): bool {
                self::assertSame('Subject', $message->getSubject());
                self::assertSame('from@example.com', $message->getFrom()[0]->getAddress());
                self::assertSame('to@example.com', $message->getTo()[0]->getAddress());
                self::assertStringContainsString('file.txt', $message->toString());

                return true;
            }));
        $notifier = new SmtpEmailNotifier($mailer, new EmailMessageFactory());

        $result = $notifier->notify(self::email());

        self::assertTrue($result->has('email'));
        self::assertNull($result->messageId('email'));
    }

    #[Test]
    public function itRejectsIncompleteEmailBeforeSending(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $notifier = new SmtpEmailNotifier($mailer, new EmailMessageFactory());

        $this->expectException(InvalidArgumentException::class);

        $notifier->notify(new EmailNotification());
    }

    #[Test]
    public function itRejectsAnUnsupportedNotification(): void
    {
        $mailer   = $this->createStub(MailerInterface::class);
        $notifier = new SmtpEmailNotifier($mailer, new EmailMessageFactory());

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    public function itTranslatesAnSmtpFailure(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP unavailable.'));
        $notifier = new SmtpEmailNotifier($mailer, new EmailMessageFactory());

        $this->expectException(NotificationProviderFailed::class);

        $notifier->notify(self::email());
    }

    #[Test]
    public function itTranslatesAnSesFailure(): void
    {
        $error    = new AwsException('SES unavailable.', new Command('SendEmail'));
        $client   = new SesV2Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => new MockHandler([$error]),
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
        $notifier = new SesEmailNotifier($client, new EmailMessageFactory());

        $this->expectException(NotificationProviderFailed::class);

        $notifier->notify(self::email());
    }

    private static function email(): EmailNotification
    {
        $notification = new EmailNotification();
        $notification->setFromAddress(Address::create('from@example.com'));
        $notification->addToAddress(Address::create('to@example.com'));
        $notification->setSubject('Subject')->setHtmlBody('<p>Body</p>');
        $notification->addAttachment(Attachment::fromString('file content', 'file.txt'));

        return $notification;
    }
}
