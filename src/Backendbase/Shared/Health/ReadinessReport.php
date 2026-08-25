<?php

declare(strict_types=1);

namespace Backendbase\Shared\Health;

use JsonSerializable;
use Override;

final readonly class ReadinessReport implements JsonSerializable
{
    /** @param list<ReadinessResult> $results */
    public function __construct(private array $results)
    {
    }

    public function isReady(): bool
    {
        foreach ($this->results as $result) {
            if (! $result->isReady()) {
                return false;
            }
        }

        return true;
    }

    /** @return array{status: 'ready'|'unavailable', checks: array<string, 'ready'|'unavailable'>} */
    #[Override]
    public function jsonSerialize(): array
    {
        $checks = [];
        foreach ($this->results as $result) {
            $checks[$result->name()] = $result->status();
        }

        return [
            'status' => $this->isReady() ? 'ready' : 'unavailable',
            'checks' => $checks,
        ];
    }
}
