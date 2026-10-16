<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Infrastructure\Adapters\Diagnostics\SafeExceptionContext;
use Backendbase\Infrastructure\Adapters\Queue\Serialization\JsonPayload;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Operation\OutboxPublicationResult;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

use function is_array;

final readonly class OutboxMessagePublisher implements OutboxPublisher
{
    public function __construct(private MessagePublisher $publisher, private LoggerInterface $logger)
    {
    }

    public function publish(ClaimedOutboxMessage $message): OutboxPublicationResult
    {
        try {
            if ($message->destination() === null || $message->occurredAt() === null) {
                throw new UnexpectedValueException('Adopt the original outbox destination and occurrence time before publication.');
            }

            $properties = JsonPayload::decode($message->payload());
            if (! is_array($properties)) {
                throw new UnexpectedValueException('The outbox payload must be a JSON object.');
            }

            $this->publisher->publish(new Message(
                $message->eventName(),
                $properties,
                $message->id(),
                $message->eventVersion(),
                destination: $message->destination(),
                occurredAt: $message->occurredAt(),
            ));

            return OutboxPublicationResult::succeeded();
        } catch (Throwable $exception) {
            $this->logger->error('Outbox message publication failed.', [
                'message_id' => $message->id(),
                ...SafeExceptionContext::from($exception),
                'event_name' => $message->eventName(),
                'event_version' => $message->eventVersion(),
            ]);

            return OutboxPublicationResult::failed();
        }
    }
}
