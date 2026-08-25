<?php

declare(strict_types=1);

namespace Tests\Shared\Primitives\Notification;

use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\Attachment;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\PushNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function base64_encode;
use function basename;
use function file_put_contents;
use function restore_error_handler;
use function set_error_handler;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class NotificationPrimitivesTest extends TestCase
{
    #[Test]
    public function itCreatesAndSerializesAddresses(): void
    {
        $address = Address::create(' user@example.com ', ' User ');
        self::assertSame(['email' => 'user@example.com', 'name' => 'User'], $address->toArray());
        self::assertSame($address->toArray(), $address->jsonSerialize());
        self::assertSame(['email' => 'user@example.com'], Address::create('user@example.com', ' ')->toArray());

        $this->expectException(InvalidArgumentException::class);
        Address::create('invalid');
    }

    #[Test]
    public function itCreatesAndSerializesAttachments(): void
    {
        $attachment = Attachment::fromString(
            'contents',
            'document.txt',
            'text/plain',
            Attachment::DISPOSITION_INLINE,
            'content-id',
        );
        self::assertSame([
            'content' => base64_encode('contents'),
            'filename' => 'document.txt',
            'disposition' => 'inline',
            'type' => 'text/plain',
            'content_id' => 'content-id',
        ], $attachment->toArray());
        self::assertSame($attachment->toArray(), $attachment->jsonSerialize());

        $minimal = Attachment::fromBase64('YQ==', 'a.txt');
        self::assertSame([
            'content' => 'YQ==',
            'filename' => 'a.txt',
            'disposition' => 'attachment',
        ], $minimal->toArray());
    }

    #[Test]
    public function itCreatesAnAttachmentFromAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backendbase-attachment-');
        self::assertIsString($path);
        file_put_contents($path, 'file contents');

        try {
            $attachment = Attachment::fromFile($path);
            self::assertSame(base64_encode('file contents'), $attachment->content);
            self::assertSame(basename($path), $attachment->filename);
        } finally {
            unlink($path);
        }

        $this->expectException(InvalidArgumentException::class);
        Attachment::fromFile('/missing/backendbase-attachment');
    }

    #[Test]
    public function itRejectsEmptyAttachmentValues(): void
    {
        foreach ([['', 'file.txt'], ['YQ==', '']] as [$content, $filename]) {
            try {
                Attachment::fromBase64($content, $filename);
                self::fail('The attachment must fail.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function itRejectsAFileThatCannotBeRead(): void
    {
        stream_wrapper_register('unreadable', UnreadableFileStream::class);
        set_error_handler(static fn (): bool => true);

        try {
            $this->expectException(RuntimeException::class);

            Attachment::fromFile('unreadable://attachment.txt');
        } finally {
            restore_error_handler();
            stream_wrapper_unregister('unreadable');
        }
    }

    #[Test]
    public function itBuildsAnEmailNotification(): void
    {
        $to         = Address::create('to@example.com', 'Recipient');
        $from       = Address::create('from@example.com');
        $attachment = Attachment::fromString('data', 'file.txt');
        $email      = new EmailNotification();
        self::assertSame($email, $email->addToAddress($to));
        self::assertSame($email, $email->setFromAddress($from));
        self::assertSame($email, $email->setSubject('Subject'));
        self::assertSame($email, $email->setHtmlBody('<p>Body</p>'));
        self::assertSame($email, $email->setTemplateId('template-id'));
        self::assertSame($email, $email->setTemplateLanguage('en'));
        self::assertSame($email, $email->setTemplateData(['name' => 'User']));
        self::assertSame($email, $email->addAttachment($attachment));

        self::assertSame([$to], $email->toAddresses());
        self::assertSame($from, $email->from());
        self::assertSame('Subject', $email->subject());
        self::assertSame('<p>Body</p>', $email->htmlBody());
        self::assertSame('template-id', $email->templateId());
        self::assertSame('en', $email->templateLanguage());
        self::assertSame(['name' => 'User'], $email->templateData());
        self::assertSame([$attachment], $email->attachments());
        self::assertSame('email', $email->type());
        self::assertSame($email->toArray(), $email->jsonSerialize());
    }

    #[Test]
    public function itBuildsPushSmsAndStackNotifications(): void
    {
        $push = new PushNotification();
        self::assertSame($push, $push->setTitle('Title')->setBody('Body'));
        self::assertSame($push, $push->setTopic('topic')->setDeviceToken('token'));
        self::assertSame($push, $push->setNotificationImage('image')->setData(['id' => '1']));
        self::assertSame('Title', $push->title());
        self::assertSame('Body', $push->body());
        self::assertSame('topic', $push->topic());
        self::assertSame('token', $push->deviceToken());
        self::assertSame('image', $push->notificationImage());
        self::assertSame(['id' => '1'], $push->data());
        self::assertSame('push', $push->type());
        self::assertSame($push->toArray(), $push->jsonSerialize());

        $sms = new SmsNotification(' +905551112233 ', 'Message');
        self::assertSame('+905551112233', $sms->phoneNumber());
        self::assertSame('Message', $sms->message());
        self::assertSame('sms', $sms->type());
        self::assertSame($sms->toArray(), $sms->jsonSerialize());

        $stack = new StackNotification();
        self::assertSame($stack, $stack->addNotification($push)->addNotification($sms));
        self::assertSame([$push, $sms], $stack->notifications());
        self::assertSame('stack', $stack->type());
        self::assertSame($stack->toArray(), $stack->jsonSerialize());
    }

    #[Test]
    public function itRejectsInvalidSmsData(): void
    {
        foreach ([['555', 'Message'], ['+905551112233', ' ']] as [$phoneNumber, $message]) {
            try {
                new SmsNotification($phoneNumber, $message);
                self::fail('The SMS notification must fail.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
