<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

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

    /** @param array<string, mixed> $data */
    public function process(array $data): QueueMessageHandlingOutcome
    {
        $consumerName = $data['topic'] ?? null;
        $messageId    = $data['messageId'] ?? null;
        try {
            $eventType = $data['messageBody'] ?? null;
            if (! is_string($eventType) || $eventType === '') {
                throw new UnexpectedValueException('The queue message event name is missing.');
            }

            if (! is_string($messageId) || $messageId === '') {
                throw new UnexpectedValueException('The queue message ID is missing.');
            }

            if (! is_string($consumerName) || $consumerName === '') {
                throw new UnexpectedValueException('The queue consumer name is missing.');
            }

            $eventVersion = $data['eventVersion'] ?? null;
            if (! is_string($eventVersion) || $eventVersion === '') {
                throw new UnexpectedValueException('The queue message event version is missing.');
            }

            $eventName = $eventType . '_Event';
            $this->inboxMessageTransaction->processOnce(
                $consumerName,
                $messageId,
                $eventName,
                function () use ($data, $eventName, $eventVersion): void {
                    $this->eventDispatcher->dispatch($eventName, $eventVersion, $data['data'] ?? null);
                },
            );
            $this->failurePolicy->succeeded($consumerName, $messageId);

            return QueueMessageHandlingOutcome::ACKNOWLEDGE;
        } catch (MappingError | UnexpectedValueException $exception) {
            $this->logger->error('Queue message mapping failed.', [
                'message_id' => $data['messageId'] ?? null,
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
        } catch (Throwable $exception) {
            $this->logger->error('Queue message processing failed.', [
                'exception' => $exception::class,
                'message_id' => $data['messageId'] ?? null,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->failurePolicy->transientFailure(
                $consumerName,
                $messageId,
                $exception::class,
            );
        }
    }
}
