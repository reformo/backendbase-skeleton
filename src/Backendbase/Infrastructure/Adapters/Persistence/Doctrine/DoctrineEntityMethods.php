<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use DateTimeImmutable;

use function get_object_vars;

use const DATE_ATOM;

trait DoctrineEntityMethods
{
    /**
     * @param array<int, string>|null $excludeColumns
     *
     * @return array<string, mixed>
     */
    public function toArray(array|null $excludeColumns = [], bool|null $includeDeletedAt = false, bool|null $getId = false): array
    {
        $excludeColumns ??= [];

        if ($getId !== true) {
            $excludeColumns[] = 'id';
        }

        if (! $includeDeletedAt) {
            $excludeColumns[] = 'deletedAt';
        }

        $values = get_object_vars($this);

        foreach ($excludeColumns as $excludeColumn) {
            unset($values[$excludeColumn]);
        }

        foreach ($values as $key => $value) {
            if (! ($value instanceof DateTimeImmutable)) {
                continue;
            }

            $values[$key] = $value->format(DATE_ATOM);
        }

        return $values;
    }
}
