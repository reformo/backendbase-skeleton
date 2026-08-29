<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Settings;

/** @phpstan-import-type RuntimeSettings from ValidatedApplicationSettings as RuntimeValues */
final readonly class ApplicationRuntimeSettings
{
    /** @var RuntimeValues */
    private array $values;

    private Environment $environment;

    public function __construct(Settings $settings)
    {
        $this->values      = ValidatedApplicationSettings::runtime($settings->get());
        $this->environment = $settings->env();
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function basePath(): string
    {
        return $this->values['basePath'];
    }

    public function cdnBaseUrl(): string
    {
        return $this->values['cdnBaseUrl'];
    }

    public function displaysErrorDetails(): bool
    {
        return $this->values['displayErrorDetails'];
    }

    public function logsErrors(): bool
    {
        return $this->values['logError'];
    }

    public function logsErrorDetails(): bool
    {
        return $this->values['logErrorDetails'];
    }

    public function routeCacheFile(): string|null
    {
        return $this->values['routeCacheFile'];
    }
}
