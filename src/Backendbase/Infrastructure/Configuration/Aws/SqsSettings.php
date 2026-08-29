<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Aws;

use Backendbase\Shared\Configuration\ValidatedAwsSettings;

use function max;
use function min;

/** @phpstan-import-type SqsSettings from ValidatedAwsSettings as SqsValues */
final readonly class SqsSettings
{
    /**
     * @var array{
     *     continuous: bool,
     *     maxNumberOfMessages: int,
     *     queueName: string|null,
     *     queueUrl: string|null,
     *     visibilityTimeoutSeconds: int,
     *     waitTimeSeconds: int
     * }
     */
    private array $values;

    /** @param SqsValues $values */
    public function __construct(array $values)
    {
        $this->values = [
            'continuous' => $values['continuous'] ?? true,
            'maxNumberOfMessages' => min(10, max(1, $values['maxNumberOfMessages'] ?? 1)),
            'queueName' => $values['queue'] ?? null,
            'queueUrl' => $values['queueUrl'] ?? null,
            'visibilityTimeoutSeconds' => min(43200, max(0, $values['visibilityTimeout'] ?? 30)),
            'waitTimeSeconds' => min(20, max(0, $values['waitTimeSeconds'] ?? 20)),
        ];
    }

    public function continuous(): bool
    {
        return $this->values['continuous'];
    }

    public function maxNumberOfMessages(): int
    {
        return $this->values['maxNumberOfMessages'];
    }

    public function queueName(): string|null
    {
        return $this->values['queueName'];
    }

    public function queueUrl(): string|null
    {
        return $this->values['queueUrl'];
    }

    public function visibilityTimeoutSeconds(): int
    {
        return $this->values['visibilityTimeoutSeconds'];
    }

    public function waitTimeSeconds(): int
    {
        return $this->values['waitTimeSeconds'];
    }
}
