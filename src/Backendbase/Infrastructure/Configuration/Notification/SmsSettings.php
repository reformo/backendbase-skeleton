<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Notification;

use UnexpectedValueException;

use function in_array;
use function is_array;

final readonly class SmsSettings
{
    private string $driver;
    private TwilioSmsSettings|NetgsmSmsSettings|null $provider;

    public function __construct(mixed $settings)
    {
        if (! is_array($settings)) {
            throw new UnexpectedValueException('SMS settings must be an array.');
        }

        $driver = $settings['driver'] ?? 'sns';
        if (! in_array($driver, ['sns', 'twilio', 'netgsm'], true)) {
            throw new UnexpectedValueException('The SMS driver must be sns, twilio, or netgsm.');
        }

        $this->driver   = $driver;
        $this->provider = match ($driver) {
            'twilio' => new TwilioSmsSettings($settings['twilio'] ?? []),
            'netgsm' => new NetgsmSmsSettings($settings['netgsm'] ?? []),
            default => null,
        };
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function twilio(): TwilioSmsSettings
    {
        if (! $this->provider instanceof TwilioSmsSettings) {
            throw new UnexpectedValueException('Twilio SMS settings are not active.');
        }

        return $this->provider;
    }

    public function netgsm(): NetgsmSmsSettings
    {
        if (! $this->provider instanceof NetgsmSmsSettings) {
            throw new UnexpectedValueException('Netgsm SMS settings are not active.');
        }

        return $this->provider;
    }
}
