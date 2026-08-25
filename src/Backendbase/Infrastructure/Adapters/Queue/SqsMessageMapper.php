<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use JsonException;

use function array_merge;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SqsMessageMapper
{
    /** @param array<string, mixed> $params */
    public static function outgoing(array $params): string
    {
        $properties = $params['properties'] ?? [];
        $attributes = $params['attributes'] ?? [];

        return json_encode([
            'messageBody' => (string) $params['messageBody'],
            'messageId' => $params['messageId'] ?? null,
            'eventVersion' => $params['eventVersion'] ?? null,
            'data' => array_merge(
                is_array($properties) ? $properties : [],
                is_array($attributes) ? $attributes : [],
            ),
            'tag' => $params['tag'] ?? $params['routingKey'] ?? null,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    public static function incoming(array $message, string $queueName): array
    {
        $body    = is_string($message['Body'] ?? null) ? $message['Body'] : '';
        $payload = self::payload($body);

        return [
            'messageBody' => $payload['messageBody'] ?? $body,
            'eventVersion' => $payload['eventVersion'] ?? null,
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
            'messageId' => $payload['messageId'] ?? $message['MessageId'] ?? null,
            'topic' => $queueName,
            'tag' => $payload['tag'] ?? $queueName,
            'keys' => is_array($message['MessageAttributes'] ?? null) ? $message['MessageAttributes'] : [],
        ];
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
