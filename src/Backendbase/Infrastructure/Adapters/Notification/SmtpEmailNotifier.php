<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\Notification;
use Override;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use UnexpectedValueException;

final readonly class SmtpEmailNotifier implements NotificationProvider
{
    public const string TYPE = 'email';

    public function __construct(private MailerInterface $mailer, private EmailMessageFactory $messageFactory)
    {
    }

    #[Override]
    public function type(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function notify(Notification $notification): NotificationResult
    {
        if (! $notification instanceof EmailNotification) {
            throw new UnexpectedValueException('SMTP requires an email notification.');
        }

        $factory = $this->messageFactory;
        $message = $factory->create($notification);
        $mailer  = $this->mailer;
        try {
            $mailer->send($message);
        } catch (TransportExceptionInterface $exception) {
            throw new NotificationProviderFailed(self::TYPE, $exception);
        }

        return NotificationResult::delivered(self::TYPE, null);
    }
}
