<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use ArrayObject;
use Backendbase\Infrastructure\Adapters\Queue\Serialization\JsonPayload;
use Backendbase\Shared\Integrations\Messaging\Message;
use JsonException;

use function is_array;
use function is_string;
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
            'occurredAt' => $message->occurredAt(),
        ], JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $message */
    public static function incoming(array $message, string $queueName): Message
    {
        $body        = self::stringOrDefault($message['Body'] ?? null, '');
        $payload     = self::payload($body);
        $messageBody = $payload['messageBody'] ?? $body;
        $messageId   = $payload['messageId'] ?? $message['MessageId'] ?? null;
        $version     = $payload['eventVersion'] ?? null;
        $routingKey  = $payload['tag'] ?? $queueName;

        return new Message(
            self::stringOrDefault($messageBody, $body),
            self::arrayOrEmpty($payload['data'] ?? null),
            self::nullableString($messageId),
            self::nullableString($version),
            $queueName,
            self::stringOrDefault($routingKey, $queueName),
            self::nullableString($payload['occurredAt'] ?? null),
        );
    }

    private static function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }

    /** @return array<string, mixed> */
    private static function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) || $value instanceof ArrayObject ? (array) $value : [];
    }

    private static function nullableString(mixed $value): string|null
    {
        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function payload(string $body): array
    {
        try {
            $payload = JsonPayload::decode($body);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }
}
