<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel;

final readonly class IdentityOperationResult
{
    /** @param array<string, string|int|bool|null> $data */
    public function __construct(private int $status, private array $data)
    {
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string|int|bool|null> */
    public function data(): array
    {
        return $this->data;
    }
}
