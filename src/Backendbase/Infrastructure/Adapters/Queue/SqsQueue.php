<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Configuration\Aws\SqsSettings;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use Backendbase\Shared\Integrations\Operation\MessagePublicationResult;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Override;
use Throwable;
use UnexpectedValueException;

use function is_array;
use function is_string;
use function max;
use function min;

final readonly class SqsQueue implements MessageConsumer, MessagePublisher
{
    public function __construct(private SqsClient $client, private SqsSettings $settings)
    {
    }

    #[Override]
    public function publish(Message $message): MessagePublicationResult
    {
        $destination = $message->destination();
        $queueUrl    = $this->queueUrl($destination);
        $messageBody = SqsMessageMapper::outgoing($message);
        $result      = $this->client->sendMessage([
            'QueueUrl' => $queueUrl,
            'MessageBody' => $messageBody,
        ]);
        $messageId   = $result['MessageId'] ?? null;

        return new MessagePublicationResult(is_string($messageId) ? $messageId : null);
    }

    /** @param callable(Message): QueueMessageHandlingOutcome $handler */
    #[Override]
    public function consume(MessageSubscription $subscription, callable $handler): void
    {
        $queueName    = $subscription->destination();
        $queueUrl     = $this->queueUrl($queueName);
        $continuous   = $subscription->continuous();
        $continuous ??= $this->settings->continuous();

        do {
            foreach ($this->receiveMessages($subscription, $queueUrl) as $message) {
                $this->handleMessage($message, $queueName, $queueUrl, $handler);
            }
        } while ($continuous);
    }

    /** @return list<array<string, mixed>> */
    private function receiveMessages(MessageSubscription $subscription, string $queueUrl): array
    {
        $maxNumberOfMessages   = $subscription->maxNumberOfMessages();
        $maxNumberOfMessages ??= $this->settings->maxNumberOfMessages();
        $visibilityTimeout     = $subscription->visibilityTimeout();
        $visibilityTimeout   ??= $this->settings->visibilityTimeoutSeconds();
        $waitTimeSeconds       = $subscription->waitTimeSeconds();
        $waitTimeSeconds     ??= $this->settings->waitTimeSeconds();
        $result                = $this->client->receiveMessage([
            'AttributeNames' => ['All'],
            'MaxNumberOfMessages' => min(10, max(1, $maxNumberOfMessages)),
            'MessageAttributeNames' => ['All'],
            'QueueUrl' => $queueUrl,
            'VisibilityTimeout' => min(43200, max(0, $visibilityTimeout)),
            'WaitTimeSeconds' => min(20, max(0, $waitTimeSeconds)),
        ]);
        $messages              = $result['Messages'] ?? [];
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
     * @param array<string, mixed>                           $message
     * @param callable(Message): QueueMessageHandlingOutcome $handler
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

    private function queueUrl(string|null $queueName): string
    {
        $queueUrl = $this->settings->queueUrl();
        if ($queueUrl !== null && $queueUrl !== '') {
            return $queueUrl;
        }

        $queueName = $this->queueName($queueName);
        $result    = $this->client->getQueueUrl(['QueueName' => $queueName]);
        $queueUrl  = $result['QueueUrl'] ?? null;
        if (! is_string($queueUrl) || $queueUrl === '') {
            throw new UnexpectedValueException('The SQS queue URL was not resolved.');
        }

        return $queueUrl;
    }

    private function queueName(string|null $queueName): string
    {
        $queueName ??= $this->settings->queueName();
        if ($queueName === null || $queueName === '') {
            throw new UnexpectedValueException('The SQS queue name is missing.');
        }

        return $queueName;
    }
}
