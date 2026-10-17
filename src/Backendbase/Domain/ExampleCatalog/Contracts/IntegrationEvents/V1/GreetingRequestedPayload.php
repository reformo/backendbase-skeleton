<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Override;

final readonly class GreetingRequestedPayload implements EventMessage
{
    public const array INPUT_FIELD_ALIASES = ['fullname' => 'fullName'];

    public function __construct(private string $fullName)
    {
    }

    public function fullName(): string
    {
        return $this->fullName;
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
