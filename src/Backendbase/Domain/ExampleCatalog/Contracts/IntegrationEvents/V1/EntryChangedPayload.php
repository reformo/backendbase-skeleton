<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Override;

final readonly class EntryChangedPayload implements EventMessage
{
    /** @param array<string, mixed>|null $details */
    public function __construct(
        private string $exampleId,
        private bool|null $isActive,
        private string|null $value,
        private array|null $details,
    ) {
    }

    public function entryId(): string
    {
        return $this->exampleId;
    }

    /**
     * @return array{
     *     exampleId: string,
     *     command: array{
     *         isActive: bool|null,
     *         value: string|null,
     *         details: array<string, mixed>|null,
     *         exampleId: string
     *     }
     * }
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'exampleId' => $this->exampleId,
            'command' => [
                'isActive' => $this->isActive,
                'value' => $this->value,
                'details' => $this->details,
                'exampleId' => $this->exampleId,
            ],
        ];
    }

    /**
     * @return array{
     *     exampleId: string,
     *     command: array{
     *         isActive: bool|null,
     *         value: string|null,
     *         details: array<string, mixed>|null,
     *         exampleId: string
     *     }
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
