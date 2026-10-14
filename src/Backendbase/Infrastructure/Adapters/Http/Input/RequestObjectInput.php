<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Input;

use Backendbase\Shared\Exception\InvalidUserInput;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

use function array_diff_key;
use function array_fill_keys;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ltrim;
use function str_starts_with;
use function strlen;

use const JSON_THROW_ON_ERROR;

final class RequestObjectInput
{
    private const int MAX_ENCODED_BYTES = 65536;

    /**
     * @param list<string> $allowedFields
     *
     * @return array<string, mixed>
     */
    public static function read(mixed $payload, array $allowedFields): array
    {
        if (! is_array($payload)) {
            throw InvalidUserInput::create('The request body must be a JSON object.');
        }

        $unknownFields = array_diff_key($payload, array_fill_keys($allowedFields, true));
        if ($unknownFields !== []) {
            throw InvalidUserInput::create('The request body contains unknown fields.');
        }

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidUserInput::create('The request body must contain valid JSON values.', previous: $exception);
        }

        if (strlen($encoded) > self::MAX_ENCODED_BYTES) {
            throw InvalidUserInput::create('The encoded request object must not exceed 65536 bytes.');
        }

        $result = [];
        foreach ($payload as $field => $value) {
            if (! is_string($field)) {
                throw InvalidUserInput::create('Request object fields must have string names.');
            }

            $result[$field] = $value;
        }

        return $result;
    }

    /**
     * @param list<string> $allowedFields
     *
     * @return array<string, mixed>
     */
    public static function fromRequest(ServerRequestInterface $request, array $allowedFields): array
    {
        $encoded = self::boundedBody($request);
        try {
            $payload = json_decode($encoded, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidUserInput::create('The request body must contain valid JSON.', previous: $exception);
        }

        if (! str_starts_with(ltrim($encoded), '{')) {
            throw InvalidUserInput::create('The request body must be a JSON object.');
        }

        return self::read($payload, $allowedFields);
    }

    public static function boundedBody(ServerRequestInterface $request): string
    {
        $body = $request->getBody();
        if (! $body->isSeekable()) {
            throw InvalidUserInput::create('The request body must support bounded JSON reading.');
        }

        $body->rewind();
        $encoded = $body->read(self::MAX_ENCODED_BYTES + 1);
        $body->rewind();
        if (strlen($encoded) > self::MAX_ENCODED_BYTES) {
            throw InvalidUserInput::create('The request body must not exceed 65536 bytes.');
        }

        return $encoded;
    }

    public static function assertEmptyBody(ServerRequestInterface $request): void
    {
        self::read($request->getParsedBody() ?? [], []);
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        if ($body->read(1) !== '') {
            throw InvalidUserInput::create('This operation does not accept a request body.');
        }
    }

    public static function positiveIntegerOrNull(mixed $value, string $name): int|null
    {
        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 1) {
            throw InvalidUserInput::create('The ' . $name . ' value must be a positive integer.');
        }

        return $value;
    }
}
