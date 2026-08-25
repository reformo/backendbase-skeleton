<?php

declare(strict_types=1);

namespace Backendbase\Shared\Health;

interface ReadinessCheck
{
    /** @return non-empty-string */
    public function name(): string;

    public function check(): void;
}
