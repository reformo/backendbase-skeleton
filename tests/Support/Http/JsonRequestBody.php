<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\StreamInterface;

use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

final class JsonRequestBody
{
    /** @param array<string, mixed> $payload */
    public static function create(array $payload): StreamInterface
    {
        $factory = new StreamFactory();

        return $factory->createStream(json_encode((object) $payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
