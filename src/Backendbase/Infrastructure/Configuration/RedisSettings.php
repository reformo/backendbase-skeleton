<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use Backendbase\Shared\Settings;

final readonly class RedisSettings
{
    /** @var array{host: string, port: int, username: string, password: string, tls: bool, readinessTimeoutSeconds: float} */
    private array $values;

    public function __construct(Settings $settings)
    {
        $redis     = ValidatedApplicationSettings::redis($settings->get('redis'));
        $readiness = ValidatedApplicationSettings::readiness($settings->get('readiness'));

        $this->values = [
            'host' => $redis['host'],
            'port' => $redis['port'],
            'username' => $redis['username'],
            'password' => $redis['password'],
            'tls' => $redis['tls'],
            'readinessTimeoutSeconds' => $readiness['timeout-seconds'],
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

    public function readinessTimeoutSeconds(): float
    {
        return $this->values['readinessTimeoutSeconds'];
    }

    public function connectionHost(): string
    {
        return ($this->values['tls'] ? 'tls://' : '') . $this->values['host'];
    }

    /** @return array{stream: array{verify_peer: true, verify_peer_name: true}}|null */
    public function connectionContext(): array|null
    {
        return $this->values['tls'] ? ['stream' => ['verify_peer' => true, 'verify_peer_name' => true]] : null;
    }

    /** @return string|array{string, string}|null */
    public function credentials(): string|array|null
    {
        if ($this->values['password'] === '') {
            return null;
        }

        return $this->values['username'] === '' ? $this->values['password'] : [$this->values['username'], $this->values['password']];
    }
}
