<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

final readonly class OutboxRelayResult
{
    public function __construct(private int $published, private int $failed)
    {
    }

    public function published(): int
    {
        return $this->published;
    }

    public function failed(): int
    {
        return $this->failed;
    }
}
