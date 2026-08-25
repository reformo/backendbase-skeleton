<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

interface ReadModel
{
    /** @return array<array-key, mixed>|null */
    public function run(mixed $options = []): array|null;
}
