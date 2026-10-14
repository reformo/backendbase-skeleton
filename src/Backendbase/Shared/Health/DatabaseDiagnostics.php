<?php

declare(strict_types=1);

namespace Backendbase\Shared\Health;

interface DatabaseDiagnostics
{
    /** @return array<string, int|float|string> */
    public function inspect(): array;
}
