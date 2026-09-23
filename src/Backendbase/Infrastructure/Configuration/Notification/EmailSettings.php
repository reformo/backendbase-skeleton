<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Notification;

use UnexpectedValueException;

use function in_array;
use function is_array;

final readonly class EmailSettings
{
    private string $driver;
    private SmtpSettings|null $smtp;

    public function __construct(mixed $settings)
    {
        if (! is_array($settings)) {
            throw new UnexpectedValueException('Email settings must be an array.');
        }

        $driver = $settings['driver'] ?? 'ses';
        if (! in_array($driver, ['ses', 'smtp'], true)) {
            throw new UnexpectedValueException('The email driver must be ses or smtp.');
        }

        $this->driver = $driver;
        $this->smtp   = $driver === 'smtp' ? new SmtpSettings($settings['smtp'] ?? []) : null;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function smtp(): SmtpSettings
    {
        if ($this->smtp === null) {
            throw new UnexpectedValueException('SMTP settings are not active.');
        }

        return $this->smtp;
    }
}
