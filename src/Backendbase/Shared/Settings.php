<?php

declare(strict_types=1);

namespace Backendbase\Shared;

use Backendbase\Shared\Options\System\Environment;

interface Settings
{
    public function get(string $key = ''): mixed;

    public function env(): Environment;
}
