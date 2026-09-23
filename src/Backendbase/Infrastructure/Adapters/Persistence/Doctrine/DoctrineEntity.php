<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

interface DoctrineEntity
{
    public function id(): int|string;

    /**
     * @param array<int, string>|null $excludeColumns
     *
     * @return array<string, mixed>
     */
    public function toArray(array|null $excludeColumns = [], bool|null $includeDeletedAt = false, bool|null $getId = false): array;
}
