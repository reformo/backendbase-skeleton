<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Messaging;

final readonly class MessageSubscription
{
    /**
     * @var array{
     *     destination: string,
     *     waitTimeSeconds: float|null,
     *     maxNumberOfMessages: int|null,
     *     visibilityTimeout: int|null,
     *     continuous: bool|null
     * }
     */
    private array $values;

    public function __construct(
        string $destination,
        float|null $waitTimeSeconds = null,
        int|null $maxNumberOfMessages = null,
        int|null $visibilityTimeout = null,
        bool|null $continuous = null,
    ) {
        $this->values = [
            'destination' => $destination,
            'waitTimeSeconds' => $waitTimeSeconds,
            'maxNumberOfMessages' => $maxNumberOfMessages,
            'visibilityTimeout' => $visibilityTimeout,
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

    public function maxNumberOfMessages(): int|null
    {
        return $this->values['maxNumberOfMessages'];
    }

    public function visibilityTimeout(): int|null
    {
        return $this->values['visibilityTimeout'];
    }

    public function continuous(): bool|null
    {
        return $this->values['continuous'];
    }
}
