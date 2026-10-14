<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Primitives\Notification\Notification;

interface ReliableNotificationDelivery
{
    public function send(string $consumerName, string $messageId, Notification $notification): void;
}
