<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\InboxMessageTransaction;
use CuyZ\Valinor\Mapper\MappingError;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

use function is_string;

final readonly class ExternalIntegrationEventMessageProcessor
{
    public function __construct(
        private ExternalIntegrationEventDispatcher $eventDispatcher,
        private InboxMessageTransaction $inboxMessageTransaction,
        private QueueMessageFailurePolicy $failurePolicy,
        private LoggerInterface $logger,
    ) {
    }

    public function process(Message $message): QueueMessageHandlingOutcome
    {
        $consumerName = $message->destination();
        $messageId    = $message->id();
        try {
            [$eventType, $eventVersion, $validatedConsumerName, $validatedMessageId] = $this->metadata($message);
        } catch (UnexpectedValueException $exception) {
            $this->logger->error('Queue message mapping failed.', [
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);

            if (! is_string($consumerName) || ! is_string($messageId)) {
                return QueueMessageHandlingOutcome::REJECT;
            }

            return $this->failurePolicy->permanentFailure(
                $consumerName,
                $messageId,
                $exception::class,
            );
        }

        try {
            $eventName   = $eventType . '_Event';
            $messageData = $message->data();
            $this->inboxMessageTransaction->processOnce(
                $validatedConsumerName,
                $validatedMessageId,
                $eventName,
                function () use ($messageData, $eventName, $eventVersion): void {
                    $this->eventDispatcher->dispatch($eventName, $eventVersion, $messageData);
                },
            );
            $this->failurePolicy->succeeded($validatedConsumerName, $validatedMessageId);

            return QueueMessageHandlingOutcome::ACKNOWLEDGE;
        } catch (MappingError | UnexpectedValueException $exception) {
            $this->logger->error('Queue message mapping failed.', [
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);

            return $this->failurePolicy->permanentFailure(
                $validatedConsumerName,
                $validatedMessageId,
                $exception::class,
            );
        } catch (Throwable $exception) {
            $this->logger->error('Queue message processing failed.', [
                'exception' => $exception::class,
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->failurePolicy->transientFailure(
                $validatedConsumerName,
                $validatedMessageId,
                $exception::class,
            );
        }
    }

    /** @return array{string, string, string, string} */
    private function metadata(Message $message): array
    {
        $eventType = $message->body();
        if ($eventType === '') {
            throw new UnexpectedValueException('The queue message event name is missing.');
        }

        $messageId = $message->id();
        if (! is_string($messageId) || $messageId === '') {
            throw new UnexpectedValueException('The queue message ID is missing.');
        }

        $consumerName = $message->destination();
        if (! is_string($consumerName) || $consumerName === '') {
            throw new UnexpectedValueException('The queue consumer name is missing.');
        }

        $eventVersion = $message->eventVersion();
        if (! is_string($eventVersion) || $eventVersion === '') {
            throw new UnexpectedValueException('The queue message event version is missing.');
        }

        return [$eventType, $eventVersion, $consumerName, $messageId];
    }
}
