<?php

declare(strict_types=1);

namespace Tests\Tools;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class MessageViewerFixture
{
    /** @return array<string, mixed> */
    public static function email(string $identifier = 'email-1', int $timestamp = 1_792_170_690): array
    {
        return [
            'MessageId' => $identifier,
            'Source' => 'sender@example.test',
            'To' => ['recipient@example.test'],
            'CC' => [],
            'BCC' => [],
            'Subject' => 'Verification: doğrulama',
            'BodyText' => 'Code: 123456',
            'BodyHtml' => '<p style="color:#123456">Code: <b>123456</b></p>',
            'Timestamp' => $timestamp,
        ];
    }

    /** @param list<array<string, mixed>> $emails */
    public static function client(array $emails): Client
    {
        $payload = json_encode(['messages' => ['000000000000' => $emails]], JSON_THROW_ON_ERROR);

        return new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], $payload)]))]);
    }

    /** @return array<string, string> */
    public static function request(string $path): array
    {
        return [
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PORT' => '8083',
            'HTTP_HOST' => '127.0.0.1:8083',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $path,
        ];
    }
}
