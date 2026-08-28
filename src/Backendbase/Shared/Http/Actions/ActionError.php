<?php

declare(strict_types=1);

namespace Backendbase\Shared\Http\Actions;

use JsonSerializable;
use Override;

use function count;

class ActionError implements JsonSerializable
{
    public const string BAD_REQUEST             = 'BAD_REQUEST';
    public const string INSUFFICIENT_PRIVILEGES = 'INSUFFICIENT_PRIVILEGES';
    public const string NOT_ALLOWED             = 'NOT_ALLOWED';
    public const string NOT_IMPLEMENTED         = 'NOT_IMPLEMENTED';
    public const string RESOURCE_NOT_FOUND      = 'RESOURCE_NOT_FOUND';
    public const string SERVER_ERROR            = 'SERVER_ERROR';
    public const string UNAUTHENTICATED         = 'UNAUTHENTICATED';
    public const string VALIDATION_ERROR        = 'VALIDATION_ERROR';
    public const string VERIFICATION_ERROR      = 'VERIFICATION_ERROR';

    /** @param array<string, mixed>|null $additionalData */
    public function __construct(
        private int $status,
        private string $title,
        private string $code,
        private string $type,
        private string|null $description,
        private array|null $additionalData = [],
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function setDescription(string|null $description = null): self
    {
        $this->description = $description;

        return $this;
    }

    /** @param array<string, mixed>|null $additionalData */
    public function setAdditionalData(array|null $additionalData = null): self
    {
        $this->additionalData = $additionalData;

        return $this;
    }

    /** @return array<string, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        $returnData =  [
            'type' => $this->type,
            'code' => $this->code,
            'title' => $this->title,
            'status' => $this->status(),
            'detail' => $this->description,
        ];
        if ($this->additionalData !== null && count($this->additionalData) > 0) {
            $returnData += $this->additionalData;
        }

        return $returnData;
    }
}
