<?php

declare(strict_types=1);

namespace Backendbase\MessageViewer;

use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function array_is_list;
use function array_slice;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function sprintf;
use function strlen;
use function usort;

use const JSON_THROW_ON_ERROR;

/** @return list<array{id: string, sender: string, to: list<string>, cc: list<string>, bcc: list<string>, subject: string, timestamp: string, text: string, html: string}> */
function readEmails(ClientInterface $client, int $port): array
{
    try {
        $response = $client->request('GET', 'http://127.0.0.1:' . $port . '/_ministack/ses/messages', [
            'connect_timeout' => 1,
            'timeout' => 5,
            'read_timeout' => 5,
            'allow_redirects' => false,
            'proxy' => '',
            'stream' => true,
        ]);
        $content  = readEmailResponse($response);
    } catch (GuzzleException | RuntimeException $exception) {
        throw new RuntimeException('Cannot read captured emails. Check MiniStack and BACKENDBASE_DEV_AWS_PORT.', 0, $exception);
    }

    if (strlen($content) > 8_388_608) {
        throw new RuntimeException('Captured emails exceed the viewer limit of 8 MiB.');
    }

    return parseEmails($content);
}

function readEmailResponse(ResponseInterface $response): string
{
    $body = $response->getBody();
    if ($response->getStatusCode() !== 200) {
        $body->close();

        throw new RuntimeException('Unexpected MiniStack response.');
    }

    return readEmailBody($body);
}

function readEmailBody(StreamInterface $body): string
{
    try {
        return Utils::copyToString($body, 8_388_609);
    } finally {
        $body->close();
    }
}

/** @return list<array{id: string, sender: string, to: list<string>, cc: list<string>, bcc: list<string>, subject: string, timestamp: string, text: string, html: string}> */
function parseEmails(string $content): array
{
    try {
        $payload = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('MiniStack returned invalid email data.', 0, $exception);
    }

    if (! is_array($payload) || ! is_array($payload['messages'] ?? null)) {
        throw new RuntimeException('MiniStack returned an invalid email collection.');
    }

    $messages = [];
    foreach ($payload['messages'] as $records) {
        $messages = [...$messages, ...mapEmails($records)];
    }

    usort($messages, static fn (array $first, array $second): int => $second['timestamp'] <=> $first['timestamp']);

    return array_slice($messages, 0, 200);
}

/** @return list<array{id: string, sender: string, to: list<string>, cc: list<string>, bcc: list<string>, subject: string, timestamp: string, text: string, html: string}> */
function mapEmails(mixed $records): array
{
    if (! is_array($records) || ! array_is_list($records)) {
        throw new RuntimeException('MiniStack returned an invalid email collection.');
    }

    $messages = [];
    foreach ($records as $record) {
        $messages[] = mapEmail($record);
    }

    return $messages;
}

/** @return array{id: string, sender: string, to: list<string>, cc: list<string>, bcc: list<string>, subject: string, timestamp: string, text: string, html: string} */
function mapEmail(mixed $record): array
{
    if (! is_array($record)) {
        throw new RuntimeException('MiniStack returned an invalid email record.');
    }

    return [
        'id' => emailIdentifier($record),
        'sender' => emailString($record, 'Source'),
        'to' => emailRecipients($record, 'To'),
        'cc' => emailRecipients($record, 'CC'),
        'bcc' => emailRecipients($record, 'BCC'),
        'subject' => emailString($record, 'Subject'),
        'timestamp' => emailTimestamp($record['Timestamp'] ?? null),
        'text' => emailString($record, 'BodyText'),
        'html' => emailString($record, 'BodyHtml'),
    ];
}

/** @param array<array-key, mixed> $record */
function emailIdentifier(array $record): string
{
    $identifier = emailString($record, 'MessageId');
    if ($identifier === '' || strlen($identifier) > 200) {
        throw new RuntimeException('MiniStack returned an invalid email identifier.');
    }

    return $identifier;
}

/** @param array<array-key, mixed> $record */
function emailString(array $record, string $field): string
{
    $value = $record[$field] ?? null;
    if ($field === 'BodyHtml' && $value === null) {
        return '';
    }

    if (! is_string($value)) {
        throw new RuntimeException('MiniStack returned an invalid email field.');
    }

    return $value;
}

/**
 * @param array<array-key, mixed> $record
 *
 * @return list<string>
 */
function emailRecipients(array $record, string $field): array
{
    $value = $record[$field] ?? null;
    if (! is_array($value) || ! array_is_list($value)) {
        throw new RuntimeException('MiniStack returned invalid email recipients.');
    }

    $recipients = [];
    foreach ($value as $recipient) {
        $recipients[] = emailString(['recipient' => $recipient], 'recipient');
    }

    return $recipients;
}

function emailTimestamp(mixed $value): string
{
    if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0 || $value > 253_402_300_799) {
        throw new RuntimeException('MiniStack returned an invalid email timestamp.');
    }

    $timestamp = new DateTimeImmutable('@' . sprintf('%.6F', $value));

    return $timestamp->format('Y-m-d\TH:i:s.u\Z');
}
