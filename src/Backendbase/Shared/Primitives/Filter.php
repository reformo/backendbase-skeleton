<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

readonly class Filter
{
    /**
     * @param array<int, string>        $targetFields
     * @param array<string, mixed>|null $criteria
     */
    public function __construct(private string $query, private array $targetFields, private array|null $criteria = [])
    {
    }

    public function query(): string
    {
        return $this->query;
    }

    /** @return array<int, string> */
    public function targetFields(): array
    {
        return $this->targetFields;
    }

    /** @return array<string, mixed> */
    public function criteria(): array
    {
        return $this->criteria ?? [];
    }
}
