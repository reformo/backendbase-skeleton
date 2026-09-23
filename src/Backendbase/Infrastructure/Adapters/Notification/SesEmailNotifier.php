<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\Notification;
use Override;
use UnexpectedValueException;

use function is_string;

final readonly class SesEmailNotifier implements NotificationProvider
{
    public const string TYPE = 'email';

    public function __construct(private SesV2Client $client, private EmailMessageFactory $messageFactory)
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
            throw new UnexpectedValueException('SES requires an email notification.');
        }

        $factory = $this->messageFactory;
        $message = $factory->create($notification);
        $sender  = $notification->from();
        $raw     = $message->toString();
        $client  = $this->client;
        try {
            $result = $client->sendEmail([
                'FromEmailAddress' => $sender->email,
                'Content' => ['Raw' => ['Data' => $raw]],
            ]);
        } catch (AwsException $exception) {
            throw new NotificationProviderFailed(self::TYPE, $exception);
        }

        $messageId = $result['MessageId'] ?? null;
        if (! is_string($messageId) || $messageId === '') {
            throw new UnexpectedValueException('SES returned no message identifier.');
        }

        return NotificationResult::delivered(self::TYPE, $messageId);
    }
}
