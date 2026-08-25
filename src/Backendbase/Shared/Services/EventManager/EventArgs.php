<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services\EventManager;

use stdClass;

interface EventArgs
{
    public function eventName(): string;

    public function get(): stdClass;
}
