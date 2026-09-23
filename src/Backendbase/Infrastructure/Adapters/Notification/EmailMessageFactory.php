<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\Attachment;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use InvalidArgumentException;
use Symfony\Component\Mime\Address as Mailbox;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

use function base64_decode;
use function trim;

final class EmailMessageFactory
{
    public function create(EmailNotification $notification): Email
    {
        self::validate($notification);
        $sender  = self::mailbox($notification->from());
        $subject = $notification->subject();
        $body    = $notification->htmlBody();
        $message = new Email();
        $message->from($sender);
        $message->subject($subject);
        $message->html($body);

        $this->addRecipients($message, $notification);
        $this->addAttachments($message, $notification);

        return $message;
    }

    private function addRecipients(Email $message, EmailNotification $notification): void
    {
        foreach ($notification->toAddresses() as $recipient) {
            $mailbox = self::mailbox($recipient);
            $message->addTo($mailbox);
        }
    }

    private function addAttachments(Email $message, EmailNotification $notification): void
    {
        foreach ($notification->attachments() as $attachment) {
            $part = self::part($attachment);
            $message->addPart($part);
        }
    }

    private static function mailbox(Address $address): Mailbox
    {
        return new Mailbox($address->email, $address->name ?? '');
    }

    private static function validate(EmailNotification $notification): void
    {
        if ($notification->toAddresses() === []) {
            throw new InvalidArgumentException('An email recipient is required.');
        }

        $subject = $notification->subject();
        $body    = $notification->htmlBody();
        if (trim($subject) === '' || trim($body) === '') {
            throw new InvalidArgumentException('An email subject and HTML body are required.');
        }
    }

    private static function part(Attachment $attachment): DataPart
    {
        $content = base64_decode($attachment->content, true);
        if ($content === false) {
            throw new InvalidArgumentException('Attachment content must use Base64 encoding.');
        }

        $part = new DataPart($content, $attachment->filename, $attachment->type);
        if ($attachment->disposition !== Attachment::DISPOSITION_INLINE) {
            return $part;
        }

        $part->asInline();
        if ($attachment->contentId !== null) {
            $part->setContentId($attachment->contentId);
        }

        return $part;
    }
}
