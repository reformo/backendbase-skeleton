<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use Backendbase\Shared\Settings;

use function ceil;
use function max;

final readonly class DatabaseSettings
{
    private string $dsn;
    private int $connectionTimeoutSeconds;

    public function __construct(Settings $settings)
    {
        $database                       = ValidatedApplicationSettings::database($settings->get('doctrine'));
        $readiness                      = ValidatedApplicationSettings::readiness($settings->get('readiness'));
        $this->dsn                      = $database['connect'];
        $this->connectionTimeoutSeconds = max(
            1,
            (int) ceil($readiness['timeoutSeconds']),
        );
    }

    public function dsn(): string
    {
        return $this->dsn;
    }

    public function connectionTimeoutSeconds(): int
    {
        return $this->connectionTimeoutSeconds;
    }
}
