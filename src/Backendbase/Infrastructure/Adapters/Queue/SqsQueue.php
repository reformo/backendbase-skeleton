<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

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

use function floor;
use function is_string;
use function max;
use function min;

final readonly class SqsQueue implements MessageConsumer, MessagePublisher
{
    public function __construct(private SqsTransport $transport, private SqsSettings $settings)
    {
    }

    #[Override]
    public function publish(Message $message): MessagePublicationResult
    {
        $destination = $message->destination();
        $queueUrl    = $this->queueUrl($destination);
        $messageBody = SqsMessageMapper::outgoing($message);
        $messageId   = $this->transport->publish($queueUrl, $messageBody);

        return new MessagePublicationResult($messageId);
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

        return $this->transport->receive(
            $queueUrl,
            min(10, max(1, $maxNumberOfMessages)),
            min(43200, max(0, $visibilityTimeout)),
            $this->normalizeSqsWaitTimeSeconds($waitTimeSeconds),
        );
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
        } catch (Throwable $exception) {
            $messageId = $message['MessageId'] ?? null;
            $messageId = is_string($messageId) ? $messageId : null;
            $this->transport->reportHandlingFailure($exception, $queueName, $messageId);

            return;
        }

        if ($outcome !== QueueMessageHandlingOutcome::ACKNOWLEDGE) {
            return;
        }

        $receiptHandle = $message['ReceiptHandle'] ?? null;
        if (! is_string($receiptHandle) || $receiptHandle === '') {
            throw new UnexpectedValueException('The SQS receipt handle is missing.');
        }

        $this->transport->acknowledge($queueUrl, $receiptHandle);
    }

    private function queueUrl(string|null $queueName): string
    {
        $queueUrl = $this->settings->queueUrl();
        if ($queueUrl !== null && $queueUrl !== '') {
            return $queueUrl;
        }

        $queueName = $this->queueName($queueName);
        $queueUrl  = $this->transport->queueUrl($queueName);
        if ($queueUrl === null || $queueUrl === '') {
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

    private function normalizeSqsWaitTimeSeconds(float|int $waitTimeSeconds): int
    {
        if ($waitTimeSeconds <= 0) {
            return 0;
        }

        if ($waitTimeSeconds >= 20) {
            return 20;
        }

        return (int) floor($waitTimeSeconds);
    }
}
