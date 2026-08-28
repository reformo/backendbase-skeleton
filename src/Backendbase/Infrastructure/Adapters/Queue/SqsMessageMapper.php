<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Shared\Integrations\Messaging\Message;
use JsonException;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SqsMessageMapper
{
    public static function outgoing(Message $message): string
    {
        return json_encode([
            'messageBody' => $message->body(),
            'messageId' => $message->id(),
            'eventVersion' => $message->eventVersion(),
            'data' => $message->data(),
            'tag' => $message->routingKey(),
        ], JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $message */
    public static function incoming(array $message, string $queueName): Message
    {
        $body        = is_string($message['Body'] ?? null) ? $message['Body'] : '';
        $payload     = self::payload($body);
        $messageBody = $payload['messageBody'] ?? $body;
        $messageId   = $payload['messageId'] ?? $message['MessageId'] ?? null;
        $version     = $payload['eventVersion'] ?? null;
        $routingKey  = $payload['tag'] ?? $queueName;

        return new Message(
            is_string($messageBody) ? $messageBody : $body,
            is_array($payload['data'] ?? null) ? $payload['data'] : [],
            is_string($messageId) ? $messageId : null,
            is_string($version) ? $version : null,
            $queueName,
            is_string($routingKey) ? $routingKey : $queueName,
        );
    }

    /** @return array<string, mixed> */
    private static function payload(string $body): array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }
}
