<?php

declare(strict_types=1);

namespace Backendbase\Shared\Time;

use DateTimeImmutable;

interface Clock
{
    /** Return the current instant in UTC. */
    public function now(): DateTimeImmutable;
}
