<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Override;

final readonly class GreetingRequestedPayload implements EventMessage
{
    public function __construct(private string $fullname)
    {
    }

    public function fullName(): string
    {
        return $this->fullname;
    }

    /** @return array{fullname: string} */
    public function toArray(): array
    {
        return ['fullname' => $this->fullname];
    }

    /** @return array{fullname: string} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
