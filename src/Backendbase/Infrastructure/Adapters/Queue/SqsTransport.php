<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Aws\Sqs\SqsClient;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_array;
use function is_string;

final readonly class SqsTransport
{
    public function __construct(private SqsClient $client, private LoggerInterface $logger)
    {
    }

    public function publish(string $queueUrl, string $messageBody): string|null
    {
        $result    = $this->client->sendMessage([
            'QueueUrl' => $queueUrl,
            'MessageBody' => $messageBody,
        ]);
        $messageId = $result['MessageId'] ?? null;

        return is_string($messageId) ? $messageId : null;
    }

    /** @return list<array<string, mixed>> */
    public function receive(
        string $queueUrl,
        int $maxNumberOfMessages,
        int $visibilityTimeout,
        int $waitTimeSeconds,
    ): array {
        $result   = $this->client->receiveMessage([
            'AttributeNames' => ['All'],
            'MaxNumberOfMessages' => $maxNumberOfMessages,
            'MessageAttributeNames' => ['All'],
            'QueueUrl' => $queueUrl,
            'VisibilityTimeout' => $visibilityTimeout,
            'WaitTimeSeconds' => $waitTimeSeconds,
        ]);
        $messages = $result['Messages'] ?? [];
        if (! is_array($messages)) {
            return [];
        }

        $validMessages = [];
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $validMessages[] = $message;
        }

        return $validMessages;
    }

    public function acknowledge(string $queueUrl, string $receiptHandle): void
    {
        $this->client->deleteMessage([
            'QueueUrl' => $queueUrl,
            'ReceiptHandle' => $receiptHandle,
        ]);
    }

    public function queueUrl(string $queueName): string|null
    {
        $result   = $this->client->getQueueUrl(['QueueName' => $queueName]);
        $queueUrl = $result['QueueUrl'] ?? null;

        return is_string($queueUrl) ? $queueUrl : null;
    }

    public function reportHandlingFailure(Throwable $exception, string $queueName, string|null $messageId): void
    {
        $this->logger->error('SQS message handling failed. The message remains available for retry.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'message_id' => $messageId,
            'queue_name' => $queueName,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
