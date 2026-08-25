<?php

declare(strict_types=1);

namespace Backendbase\Shared\Health;

final readonly class ReadinessResult
{
    /** @param non-empty-string $name */
    private function __construct(private string $name, private bool $ready)
    {
    }

    /** @param non-empty-string $name */
    public static function ready(string $name): self
    {
        return new self($name, true);
    }

    /** @param non-empty-string $name */
    public static function unavailable(string $name): self
    {
        return new self($name, false);
    }

    /** @return non-empty-string */
    public function name(): string
    {
        return $this->name;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    /** @return 'ready'|'unavailable' */
    public function status(): string
    {
        return $this->ready ? 'ready' : 'unavailable';
    }
}
