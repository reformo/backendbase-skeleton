<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Messaging;

final readonly class MessageSubscription
{
    /**
     * @var array{
     *     destination: string,
     *     waitTimeSeconds: float|null,
     *     maximumMessageCount: int|null,
     *     visibilityTimeoutSeconds: int|null,
     *     continuous: bool|null
     * }
     */
    private array $values;

    public function __construct(
        string $destination,
        float|null $waitTimeSeconds = null,
        int|null $maximumMessageCount = null,
        int|null $visibilityTimeoutSeconds = null,
        bool|null $continuous = null,
    ) {
        $this->values = [
            'destination' => $destination,
            'waitTimeSeconds' => $waitTimeSeconds,
            'maximumMessageCount' => $maximumMessageCount,
            'visibilityTimeoutSeconds' => $visibilityTimeoutSeconds,
            'continuous' => $continuous,
        ];
    }

    public function destination(): string
    {
        return $this->values['destination'];
    }

    public function waitTimeSeconds(): float|null
    {
        return $this->values['waitTimeSeconds'];
    }

    public function maximumMessageCount(): int|null
    {
        return $this->values['maximumMessageCount'];
    }

    public function visibilityTimeoutSeconds(): int|null
    {
        return $this->values['visibilityTimeoutSeconds'];
    }

    public function continuous(): bool|null
    {
        return $this->values['continuous'];
    }
}
