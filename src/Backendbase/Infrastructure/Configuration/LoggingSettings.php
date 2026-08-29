<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Shared\Settings;
use Monolog\Level;
use UnexpectedValueException;

use function is_array;
use function is_string;

final readonly class LoggingSettings
{
    /** @var array{name: string, path: string, level: Level} */
    private array $values;

    public function __construct(Settings $settings)
    {
        $logging = $settings->get('logger');
        if (
            ! is_array($logging)
            || ! is_string($logging['name'] ?? null)
            || ! is_string($logging['path'] ?? null)
            || ! ($logging['level'] ?? null) instanceof Level
        ) {
            throw new UnexpectedValueException('The logger settings are invalid.');
        }

        $this->values = [
            'name' => $logging['name'],
            'path' => $logging['path'],
            'level' => $logging['level'],
        ];
    }

    public function name(): string
    {
        return $this->values['name'];
    }

    public function path(): string
    {
        return $this->values['path'];
    }

    public function level(): Level
    {
        return $this->values['level'];
    }
}
