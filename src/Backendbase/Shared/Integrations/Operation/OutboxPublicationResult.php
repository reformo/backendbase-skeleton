<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

final readonly class OutboxPublicationResult
{
    private function __construct(private bool $succeeded)
    {
    }

    public static function succeeded(): self
    {
        return new self(true);
    }

    public static function failed(): self
    {
        return new self(false);
    }

    public function isSuccessful(): bool
    {
        return $this->succeeded;
    }
}
