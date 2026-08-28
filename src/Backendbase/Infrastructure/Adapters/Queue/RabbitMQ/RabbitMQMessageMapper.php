<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;

use Backendbase\Shared\Integrations\Messaging\Message;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class RabbitMQMessageMapper
{
    public static function outboundMessage(Message $message): AMQPMessage
    {
        $payload = [
            'messageBody' => $message->body(),
            'eventVersion' => $message->eventVersion(),
            'data' => $message->data(),
        ];

        return new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $message->id() ?? Uuid::uuid7()->toString(),
            ],
        );
    }

    public static function inboundMessage(AMQPMessage $message, string $queue): Message
    {
        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = ['messageBody' => $message->getBody(), 'data' => []];
        }

        if (! is_array($payload)) {
            $payload = ['messageBody' => $message->getBody(), 'data' => []];
        }

        $messageBody = $payload['messageBody'] ?? $message->getBody();
        $version     = $payload['eventVersion'] ?? null;
        $messageId   = $message->has('message_id') ? $message->get('message_id') : null;

        return new Message(
            is_string($messageBody) ? $messageBody : $message->getBody(),
            is_array($payload['data'] ?? null) ? $payload['data'] : [],
            is_string($messageId) ? $messageId : null,
            is_string($version) ? $version : null,
            $queue,
            $message->getRoutingKey(),
        );
    }
}
