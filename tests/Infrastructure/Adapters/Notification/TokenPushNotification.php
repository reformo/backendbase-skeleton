<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Primitives\Notification\PushNotification;

final class TokenPushNotification extends PushNotification
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = parent::toArray();
        unset($payload['topic']);

        return $payload;
    }
}
