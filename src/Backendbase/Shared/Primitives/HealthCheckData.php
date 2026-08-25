<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

use Backendbase\Utility\Git\Version;
use JsonSerializable;
use Override;
use stdClass;

class HealthCheckData extends stdClass implements JsonSerializable
{
    public string $buildId;
    public int $status          = 200;
    public string $statusString = 'OK';

    public function __construct()
    {
        $this->buildId = Version::short();
    }

    public function buildId(): string
    {
        return $this->buildId;
    }

    public function setBuildId(string $buildId): self
    {
        $this->buildId = $buildId;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function statusString(): string
    {
        return $this->statusString;
    }

    public function setStatusString(string $statusString): self
    {
        $this->statusString = $statusString;

        return $this;
    }

    /** @return array<string, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        return (array) $this;
    }
}
