<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Override;

final readonly class EntryAddedPayload implements EventMessage
{
    /** @param array<string, mixed> $details */
    public function __construct(
        private string $exampleId,
        private string $type,
        private int|null $typeTargetId,
        private string $group,
        private bool $isActive,
        private string $key,
        private string $value,
        private array $details,
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
     *         exampleId: string,
     *         type: string,
     *         typeTargetId: int|null,
     *         group: string,
     *         isActive: bool,
     *         key: string,
     *         value: string,
     *         details: array<string, mixed>
     *     }
     * }
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'exampleId' => $this->exampleId,
            'command' => [
                'exampleId' => $this->exampleId,
                'type' => $this->type,
                'typeTargetId' => $this->typeTargetId,
                'group' => $this->group,
                'isActive' => $this->isActive,
                'key' => $this->key,
                'value' => $this->value,
                'details' => $this->details,
            ],
        ];
    }

    /**
     * @return array{
     *     exampleId: string,
     *     command: array{
     *         exampleId: string,
     *         type: string,
     *         typeTargetId: int|null,
     *         group: string,
     *         isActive: bool,
     *         key: string,
     *         value: string,
     *         details: array<string, mixed>
     *     }
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
