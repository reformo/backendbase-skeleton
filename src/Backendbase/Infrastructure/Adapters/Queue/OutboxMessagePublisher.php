<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Messaging\Message;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class OutboxMessagePublisher
{
    public function __construct(private MessagePublisher $publisher, private LoggerInterface $logger)
    {
    }

    public function publish(string $messageId, string $eventName, string $eventVersion, string $payload): bool
    {
        try {
            $properties = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($properties)) {
                throw new UnexpectedValueException('The outbox payload must be a JSON object.');
            }

            $this->publisher->publish(new Message(
                $eventName,
                $properties,
                $messageId,
                $eventVersion,
            ));

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Outbox message publication failed.', [
                'exception' => $exception::class,
                'message_id' => $messageId,
                'event_name' => $eventName,
                'event_version' => $eventVersion,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }
}
