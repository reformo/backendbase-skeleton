<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\Notification\EmailSettings;
use Backendbase\Infrastructure\Configuration\Notification\PushSettings;
use Backendbase\Infrastructure\Configuration\Notification\SmsSettings;
use Backendbase\Shared\Settings;
use UnexpectedValueException;

use function is_array;

final readonly class NotificationSettings
{
    /** @var array{email: EmailSettings, push: PushSettings, sms: SmsSettings} */
    private array $channels;

    public function __construct(Settings $settings)
    {
        $all          = $settings->get();
        $notification = is_array($all) ? ($all['notification'] ?? []) : [];
        if (! is_array($notification)) {
            throw new UnexpectedValueException('Notification settings must be an array.');
        }

        $this->channels = [
            'email' => new EmailSettings($notification['email'] ?? []),
            'push' => new PushSettings($notification['push'] ?? []),
            'sms' => new SmsSettings($notification['sms'] ?? []),
        ];
    }

    public function email(): EmailSettings
    {
        return $this->channels['email'];
    }

    public function push(): PushSettings
    {
        return $this->channels['push'];
    }

    public function sms(): SmsSettings
    {
        return $this->channels['sms'];
    }
}
