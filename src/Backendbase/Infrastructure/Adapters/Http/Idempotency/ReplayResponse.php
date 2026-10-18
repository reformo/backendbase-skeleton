<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Idempotency;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_filter;
use function array_is_list;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

final class ReplayResponse
{
    public static function encode(ResponseInterface $response): string
    {
        $headers = [];
        foreach (['Content-Type', 'Cache-Control', 'Content-Language', 'Backendbase-Insert-Id', 'Location', 'Retry-After'] as $name) {
            $headers[$name] = $response->getHeader($name);
        }

        $headers = array_filter($headers);

        $body = (string) $response->getBody();
        if (strlen($body) > 1048576) {
            throw new RuntimeException('The response exceeds the replay storage limit.');
        }

        return json_encode(['status' => $response->getStatusCode(), 'headers' => $headers, 'body' => $body], JSON_THROW_ON_ERROR);
    }

    public static function decode(string $stored): ResponseInterface
    {
        $values = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($values) || ! is_int($values['status'] ?? null) || ! is_array($values['headers'] ?? null) || ! is_string($values['body'] ?? null)) {
            throw new RuntimeException('The stored response has an invalid shape.');
        }

        $status = $values['status'];
        if ($status < 200 || $status > 599 || strlen($values['body']) > 1048576) {
            throw new RuntimeException('The stored response exceeds its bounds.');
        }

        $headers  = self::headers($values['headers']);
        $response = new Response('php://temp', $status, $headers);
        $body     = $response->getBody();
        $body->write($values['body']);

        return $response;
    }

    /**
     * @param array<array-key, mixed> $headers
     *
     * @return array<non-empty-string, list<string>>
     */
    private static function headers(array $headers): array
    {
        $validated = [];
        foreach ($headers as $name => $values) {
            $validated[self::headerName($name)] = self::headerValues($values);
        }

        return $validated;
    }

    /** @return non-empty-string */
    private static function headerName(int|string $name): string
    {
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('The stored response header name is invalid.');
        }

        return $name;
    }

    /** @return list<string> */
    private static function headerValues(mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw new RuntimeException('The stored response headers are invalid.');
        }

        if (array_filter($values, is_string(...)) !== $values) {
            throw new RuntimeException('The stored response header value is invalid.');
        }

        return $values;
    }
}
