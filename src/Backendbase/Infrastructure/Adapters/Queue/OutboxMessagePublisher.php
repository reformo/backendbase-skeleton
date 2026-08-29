<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Operation\OutboxPublicationResult;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class OutboxMessagePublisher implements OutboxPublisher
{
    public function __construct(private MessagePublisher $publisher, private LoggerInterface $logger)
    {
    }

    public function publish(ClaimedOutboxMessage $message): OutboxPublicationResult
    {
        try {
            $properties = json_decode($message->payload(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($properties)) {
                throw new UnexpectedValueException('The outbox payload must be a JSON object.');
            }

            $this->publisher->publish(new Message(
                $message->eventName(),
                $properties,
                $message->id(),
                $message->eventVersion(),
            ));

            return OutboxPublicationResult::succeeded();
        } catch (Throwable $exception) {
            $this->logger->error('Outbox message publication failed.', [
                'exception' => $exception::class,
                'message_id' => $message->id(),
                'event_name' => $message->eventName(),
                'event_version' => $message->eventVersion(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return OutboxPublicationResult::failed();
        }
    }
}
