<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\EmailMessageFactory;
use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\Attachment;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailMessageFactoryTest extends TestCase
{
    #[Test]
    public function itRejectsAMissingSender(): void
    {
        $notification = new EmailNotification();
        $notification->addToAddress(Address::create('to@example.com'));
        $notification->setSubject('Subject')->setHtmlBody('<p>Body</p>');

        $this->expectException(InvalidArgumentException::class);

        new EmailMessageFactory()->create($notification);
    }

    #[Test]
    public function itRejectsMissingContent(): void
    {
        $notification = self::email();

        $this->expectException(InvalidArgumentException::class);

        new EmailMessageFactory()->create($notification);
    }

    #[Test]
    public function itRejectsAnInvalidBase64Attachment(): void
    {
        $notification = self::email();
        $notification->setSubject('Subject')->setHtmlBody('<p>Body</p>');
        $notification->addAttachment(Attachment::fromBase64('%%%', 'file.txt'));

        $this->expectException(InvalidArgumentException::class);

        new EmailMessageFactory()->create($notification);
    }

    #[Test]
    public function itPreservesInlineContentIdentifiers(): void
    {
        $notification = self::email();
        $notification->setSubject('Subject')->setHtmlBody('<img src="cid:image-1@example.com">');
        $notification->addAttachment(Attachment::fromString(
            'image-bytes',
            'image.png',
            'image/png',
            Attachment::DISPOSITION_INLINE,
            'image-1@example.com',
        ));

        $message = new EmailMessageFactory()->create($notification);

        self::assertStringContainsString('Content-ID: <image-1@example.com>', $message->toString());
    }

    #[Test]
    public function itRejectsAnInvalidContentIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Attachment::fromString('image-bytes', 'image.png', 'image/png', Attachment::DISPOSITION_INLINE, 'invalid');
    }

    private static function email(): EmailNotification
    {
        $notification = new EmailNotification();
        $notification->setFromAddress(Address::create('from@example.com'));
        $notification->addToAddress(Address::create('to@example.com'));

        return $notification;
    }
}
