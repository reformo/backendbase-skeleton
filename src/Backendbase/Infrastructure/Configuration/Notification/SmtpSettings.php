<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Notification;

use UnexpectedValueException;

use function in_array;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function trim;

final readonly class SmtpSettings
{
    /** @var array{host: string, port: int, username: string, password: string, encryption: string, timeoutSeconds: float} */
    private array $values;

    public function __construct(mixed $settings)
    {
        if (! is_array($settings)) {
            throw new UnexpectedValueException('SMTP settings must be an array.');
        }

        $host       = $settings['host'] ?? '';
        $port       = $settings['port'] ?? 587;
        $username   = $settings['username'] ?? '';
        $password   = $settings['password'] ?? '';
        $encryption = $settings['encryption'] ?? 'starttls';
        $timeout    = $settings['timeoutSeconds'] ?? 5.0;
        if (! is_string($host) || trim($host) === '' || ! is_int($port) || $port < 1 || $port > 65535) {
            throw new UnexpectedValueException('SMTP host or port is invalid.');
        }

        if (! is_string($username) || ! is_string($password) || ($username === '') !== ($password === '')) {
            throw new UnexpectedValueException('SMTP credentials are invalid.');
        }

        if (! in_array($encryption, ['smtps', 'starttls', 'none'], true)) {
            throw new UnexpectedValueException('SMTP encryption is invalid.');
        }

        if ((! is_float($timeout) && ! is_int($timeout)) || ! is_finite((float) $timeout) || $timeout <= 0) {
            throw new UnexpectedValueException('SMTP timeout must be positive.');
        }

        $this->values = [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'timeoutSeconds' => (float) $timeout,
        ];
    }

    public function host(): string
    {
        return $this->values['host'];
    }

    public function port(): int
    {
        return $this->values['port'];
    }

    public function username(): string
    {
        return $this->values['username'];
    }

    public function password(): string
    {
        return $this->values['password'];
    }

    public function encryption(): string
    {
        return $this->values['encryption'];
    }

    public function timeoutSeconds(): float
    {
        return $this->values['timeoutSeconds'];
    }
}
