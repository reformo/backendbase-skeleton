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
     *     'maximum-message-count': int,
     *     queueName: string|null,
     *     'queue-url': string|null,
     *     'visibility-timeout-seconds': int,
     *     'wait-time-seconds': int
     * }
     */
    private array $values;

    /** @param SqsValues $values */
    public function __construct(array $values)
    {
        $this->values = [
            'continuous' => $values['continuous'] ?? true,
            'maximum-message-count' => min(10, max(1, $values['maximum-message-count'] ?? 1)),
            'queueName' => $values['queue'] ?? null,
            'queue-url' => $values['queue-url'] ?? null,
            'visibility-timeout-seconds' => min(43200, max(0, $values['visibility-timeout-seconds'] ?? 30)),
            'wait-time-seconds' => min(20, max(0, $values['wait-time-seconds'] ?? 20)),
        ];
    }

    public function continuous(): bool
    {
        return $this->values['continuous'];
    }

    public function maximumMessageCount(): int
    {
        return $this->values['maximum-message-count'];
    }

    public function queueName(): string|null
    {
        return $this->values['queueName'];
    }

    public function queueUrl(): string|null
    {
        return $this->values['queue-url'];
    }

    public function visibilityTimeoutSeconds(): int
    {
        return $this->values['visibility-timeout-seconds'];
    }

    public function waitTimeSeconds(): int
    {
        return $this->values['wait-time-seconds'];
    }
}
