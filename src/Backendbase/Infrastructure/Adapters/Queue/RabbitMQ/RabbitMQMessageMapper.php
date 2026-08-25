<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;

use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;

use function array_merge;
use function is_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class RabbitMQMessageMapper
{
    /** @param array<string, mixed> $params */
    public static function outboundMessage(array $params): AMQPMessage
    {
        $properties = $params['properties'] ?? [];
        $attributes = $params['attributes'] ?? [];
        $payload    = [
            'messageBody' => (string) $params['messageBody'],
            'eventVersion' => $params['eventVersion'] ?? null,
            'data' => array_merge(
                is_array($properties) ? $properties : [],
                is_array($attributes) ? $attributes : [],
            ),
        ];

        return new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => (string) ($params['messageId'] ?? Uuid::uuid7()->toString()),
            ],
        );
    }

    /** @return array<string, mixed> */
    public static function inboundPayload(AMQPMessage $message, string $queue): array
    {
        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = ['messageBody' => $message->getBody(), 'data' => []];
        }

        if (! is_array($payload)) {
            $payload = ['messageBody' => $message->getBody(), 'data' => []];
        }

        return [
            'messageBody' => $payload['messageBody'] ?? $message->getBody(),
            'eventVersion' => $payload['eventVersion'] ?? null,
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
            'messageId' => $message->has('message_id') ? $message->get('message_id') : null,
            'topic' => $queue,
            'tag' => $message->getRoutingKey(),
            'keys' => [],
        ];
    }
}
