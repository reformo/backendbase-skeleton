<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Command;

use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\Command;
use Override;

final readonly class QueueGreeting implements Command
{
    public function __construct(private string $fullName, private AccessControl $accessControl)
    {
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function accessControl(): AccessControl
    {
        return $this->accessControl;
    }

    /** @return array{fullname: string} */
    public function toArray(): array
    {
        return ['fullname' => $this->fullName];
    }

    /** @return array{fullname: string} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
