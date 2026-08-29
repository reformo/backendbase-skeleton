<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Health;

use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Configuration\Aws\SqsSettings;
use Backendbase\Shared\Health\ReadinessCheck;
use Override;
use UnexpectedValueException;

use function is_string;

final readonly class SqsReadinessCheck implements ReadinessCheck
{
    public function __construct(private SqsClient $client, private SqsSettings $settings)
    {
    }

    /** @return non-empty-string */
    #[Override]
    public function name(): string
    {
        return 'queue';
    }

    #[Override]
    public function check(): void
    {
        $result   = $this->client->getQueueAttributes([
            'QueueUrl' => $this->queueUrl(),
            'AttributeNames' => ['QueueArn'],
        ]);
        $queueArn = $result['Attributes']['QueueArn'] ?? null;
        if (! is_string($queueArn) || $queueArn === '') {
            throw new UnexpectedValueException('The SQS readiness response did not include the queue ARN.');
        }
    }

    private function queueUrl(): string
    {
        $queueUrl = $this->settings->queueUrl();
        if ($queueUrl !== null && $queueUrl !== '') {
            return $queueUrl;
        }

        $queueName = $this->settings->queueName();
        if ($queueName === null || $queueName === '') {
            throw new UnexpectedValueException('The SQS readiness queue is not configured.');
        }

        $result   = $this->client->getQueueUrl(['QueueName' => $queueName]);
        $queueUrl = $result['QueueUrl'] ?? null;
        if (! is_string($queueUrl) || $queueUrl === '') {
            throw new UnexpectedValueException('The SQS readiness queue URL was not resolved.');
        }

        return $queueUrl;
    }
}
