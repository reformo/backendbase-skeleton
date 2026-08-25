<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Aws\Sqs\SqsClient;
use Backendbase\Shared\Integrations\BackendbaseQueue;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Override;
use Throwable;
use UnexpectedValueException;

use function is_array;
use function is_string;
use function max;
use function min;

final readonly class SqsQueue implements BackendbaseQueue
{
    /** @param array<string, mixed> $settings */
    public function __construct(private SqsClient $client, private array $settings)
    {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function publish(array $params): array
    {
        $result = $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl($params),
            'MessageBody' => SqsMessageMapper::outgoing($params),
        ]);

        return $result->toArray();
    }

    /**
     * @param array<string, mixed>                                        $params
     * @param callable(array<string, mixed>): QueueMessageHandlingOutcome $handler
     */
    #[Override]
    public function consume(array $params, callable $handler): void
    {
        $queueName  = $this->queueName($params);
        $queueUrl   = $this->queueUrl($params);
        $continuous = (bool) ($params['continuous'] ?? $this->settings['continuous'] ?? true);

        do {
            foreach ($this->receiveMessages($params, $queueUrl) as $message) {
                $this->handleMessage($message, $queueName, $queueUrl, $handler);
            }
        } while ($continuous);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function receiveMessages(array $params, string $queueUrl): array
    {
        $result   = $this->client->receiveMessage([
            'AttributeNames' => ['All'],
            'MaxNumberOfMessages' => min(10, max(1, (int) ($params['maxNumberOfMessages'] ?? $this->settings['maxNumberOfMessages'] ?? 1))),
            'MessageAttributeNames' => ['All'],
            'QueueUrl' => $queueUrl,
            'VisibilityTimeout' => min(43200, max(0, (int) ($params['visibilityTimeout'] ?? $this->settings['visibilityTimeout'] ?? 30))),
            'WaitTimeSeconds' => min(20, max(0, (int) ($params['waitTimeSeconds'] ?? $this->settings['waitTimeSeconds'] ?? 20))),
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

    /**
     * @param array<string, mixed>                                        $message
     * @param callable(array<string, mixed>): QueueMessageHandlingOutcome $handler
     */
    private function handleMessage(
        array $message,
        string $queueName,
        string $queueUrl,
        callable $handler,
    ): void {
        try {
            $outcome = $handler(SqsMessageMapper::incoming($message, $queueName));
        } catch (Throwable) {
            return;
        }

        if ($outcome !== QueueMessageHandlingOutcome::ACKNOWLEDGE) {
            return;
        }

        $receiptHandle = $message['ReceiptHandle'] ?? null;
        if (! is_string($receiptHandle) || $receiptHandle === '') {
            throw new UnexpectedValueException('The SQS receipt handle is missing.');
        }

        $this->client->deleteMessage(['QueueUrl' => $queueUrl, 'ReceiptHandle' => $receiptHandle]);
    }

    /** @param array<string, mixed> $params */
    private function queueUrl(array $params): string
    {
        $queueUrl = $params['queueUrl'] ?? $this->settings['queueUrl'] ?? null;
        if (is_string($queueUrl) && $queueUrl !== '') {
            return $queueUrl;
        }

        $result   = $this->client->getQueueUrl(['QueueName' => $this->queueName($params)]);
        $queueUrl = $result['QueueUrl'] ?? null;
        if (! is_string($queueUrl) || $queueUrl === '') {
            throw new UnexpectedValueException('The SQS queue URL was not resolved.');
        }

        return $queueUrl;
    }

    /** @param array<string, mixed> $params */
    private function queueName(array $params): string
    {
        $queueName = $params['queueName'] ?? $params['queue'] ?? $params['topic'] ?? $this->settings['queue'] ?? null;
        if (! is_string($queueName) || $queueName === '') {
            throw new UnexpectedValueException('The SQS queue name is missing.');
        }

        return $queueName;
    }
}
