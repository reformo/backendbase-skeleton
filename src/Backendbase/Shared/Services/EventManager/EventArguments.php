<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services\EventManager;

use Override;
use stdClass;

class EventArguments implements EventArgs
{
    private static EventArgs|null $emptyInstance = null;

    public static function getEmptyInstance(): EventArgs
    {
        return self::$emptyInstance ??= new self();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }

    #[Override]
    public function eventName(): string
    {
        return '';
    }

    #[Override]
    public function get(): stdClass
    {
        return new stdClass();
    }
}
