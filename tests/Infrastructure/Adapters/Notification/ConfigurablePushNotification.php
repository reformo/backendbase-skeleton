<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Primitives\Notification\PushNotification;

final class ConfigurablePushNotification extends PushNotification
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = parent::toArray() + [
            'android' => ['priority' => 'high'],
            'apns' => ['headers' => ['apns-push-type' => 'background']],
        ];
        unset($payload['deviceToken']);

        return $payload;
    }
}
