<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Actions;

use JsonSerializable;
use Override;

class ActionPayload implements JsonSerializable
{
    /** @param array<array-key, mixed>|object|null $data */
    public function __construct(
        private readonly int $statusCode = 200,
        private readonly array|object|null $data = null,
        private readonly ActionError|null $error = null,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<array-key, mixed>|object|null */
    public function getData(): array|object|null
    {
        return $this->data;
    }

    public function getError(): ActionError|null
    {
        return $this->error;
    }

    /** @return array<string, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        $payload = [
            'statusCode' => $this->statusCode,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            return $this->error
                ->jsonSerialize();
        }

        return $payload;
    }
}
