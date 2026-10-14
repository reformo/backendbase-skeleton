<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\ReliableNotificationDelivery;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Primitives\Notification\Notification;
use RuntimeException;

final readonly class InboxNotificationDelivery implements ReliableNotificationDelivery
{
    public function __construct(private Notify $notifications, private ExternalEffectInbox $inbox)
    {
    }

    public function send(string $consumerName, string $messageId, Notification $notification): void
    {
        $type = $notification->type();
        $this->inbox->processOnce($consumerName, $messageId, 'notification/' . $type, function () use ($notification, $type): void {
            $result = $this->notifications->notify($notification);
            if (! $result->has($type)) {
                throw new RuntimeException('The notification provider did not record complete acceptance.');
            }
        });
    }
}
