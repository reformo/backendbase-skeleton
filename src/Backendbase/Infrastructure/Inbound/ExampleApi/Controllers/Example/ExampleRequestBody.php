<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example;

use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Shared\Exception\InvalidUserInput;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ExampleRequestBody
{
    /**
     * @param list<string> $allowedFields
     *
     * @return array<string, mixed>
     */
    public static function read(ServerRequestInterface $request, array $allowedFields): array
    {
        $encoded = RequestObjectInput::boundedBody($request);
        try {
            $payload = json_decode($encoded, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidUserInput::create('The request body must contain valid JSON.', previous: $exception);
        }

        if (! $payload instanceof stdClass) {
            throw InvalidUserInput::create('The request body must be a JSON object.');
        }

        return RequestObjectInput::read((array) $payload, $allowedFields);
    }
}
