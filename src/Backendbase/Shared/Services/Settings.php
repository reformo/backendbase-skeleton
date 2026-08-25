<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services;

use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Settings as SettingsInterface;
use Override;

class Settings implements SettingsInterface
{
    /** @param array<string, mixed> $settings */
    public function __construct(private array $settings)
    {
    }

    #[Override]
    public function get(string $key = ''): mixed
    {
        return empty($key) ? $this->settings : $this->settings[$key];
    }

    #[Override]
    public function env(): Environment
    {
        return Environment::from($this->settings['env'] ?? 'dev');
    }
}
