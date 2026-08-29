<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use Backendbase\Shared\Settings;

final readonly class RedisSettings
{
    /** @var array{host: string, port: int, readinessTimeoutSeconds: float} */
    private array $values;

    public function __construct(Settings $settings)
    {
        $redis     = ValidatedApplicationSettings::redis($settings->get('redis'));
        $readiness = ValidatedApplicationSettings::readiness($settings->get('readiness'));

        $this->values = [
            'host' => $redis['host'],
            'port' => $redis['port'],
            'readinessTimeoutSeconds' => $readiness['timeoutSeconds'],
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
}
