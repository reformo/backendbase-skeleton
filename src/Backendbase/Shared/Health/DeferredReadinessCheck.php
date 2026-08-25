<?php

declare(strict_types=1);

namespace Backendbase\Shared\Health;

use Closure;
use Override;

final readonly class DeferredReadinessCheck implements ReadinessCheck
{
    /**
     * @param non-empty-string          $checkName
     * @param Closure(): ReadinessCheck $checkFactory
     */
    public function __construct(private string $checkName, private Closure $checkFactory)
    {
    }

    /** @return non-empty-string */
    #[Override]
    public function name(): string
    {
        return $this->checkName;
    }

    #[Override]
    public function check(): void
    {
        $check = ($this->checkFactory)();
        $check->check();
    }
}
