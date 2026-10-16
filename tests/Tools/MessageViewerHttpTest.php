<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Backendbase\MessageViewer\awsPort;
use function Backendbase\MessageViewer\route;
use function json_decode;
use function rawurlencode;

require_once 'bin/dev/message-viewer/http.php';

final class MessageViewerHttpTest extends TestCase
{
    public function testItServesSmsAndPrivacyHeaders(): void
    {
        $response = route(MessageViewerFixture::request('/messages'), MessageViewerFixture::client([]), static fn (): array => [
            ['timestamp' => '2026-10-16T17:41:28Z', 'recipient' => '+905551112233', 'message' => '123456'],
        ], 4566);
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('123456', $response['body']);
        self::assertSame('no-store', $response['headers']['Cache-Control']);
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $response['headers']);
        self::assertStringContainsString("frame-ancestors 'none'", $response['headers']['Content-Security-Policy']);
    }

    public function testItServesEmailMetadataAndSandboxedPreview(): void
    {
        $email    = MessageViewerFixture::email();
        $response = self::request('/emails', [$email]);
        self::assertSame(200, $response['status']);
        self::assertSame('email-1', json_decode($response['body'], true)['emails'][0]['id']);
        $preview = self::request('/email-preview?id=email-1', [$email]);
        self::assertSame(200, $preview['status']);
        self::assertStringContainsString('<b>123456</b>', $preview['body']);
        self::assertStringContainsString('sandbox;', $preview['headers']['Content-Security-Policy']);
        self::assertStringContainsString("default-src 'none'", $preview['headers']['Content-Security-Policy']);
        self::assertStringContainsString('img-src data:', $preview['headers']['Content-Security-Policy']);
        self::assertStringContainsString("form-action 'none'", $preview['headers']['Content-Security-Policy']);
    }

    #[DataProvider('rejectedRequests')]
    public function testItRejectsUnsafeRequestsBeforeReadingSources(string $path, string $key, string $value, int $status): void
    {
        $server       = MessageViewerFixture::request($path);
        $server[$key] = $value;
        $response     = route($server, MessageViewerFixture::client([]), static function (): never {
            self::fail('SMS source must not be read.');
        }, 4566);
        self::assertSame($status, $response['status']);
    }

    /** @return iterable<string, array{string, string, string, int}> */
    public static function rejectedRequests(): iterable
    {
        yield 'foreign host' => ['/messages', 'HTTP_HOST', 'example.com', 403];
        yield 'remote address' => ['/messages', 'REMOTE_ADDR', '192.0.2.1', 403];
        yield 'cross-site SMS' => ['/messages', 'HTTP_SEC_FETCH_SITE', 'cross-site', 403];
        yield 'cross-site email' => ['/emails', 'HTTP_SEC_FETCH_SITE', 'cross-site', 403];
        yield 'cross-site preview' => ['/email-preview?id=x', 'HTTP_SEC_FETCH_SITE', 'cross-site', 403];
        yield 'write method' => ['/messages', 'REQUEST_METHOD', 'POST', 405];
    }

    public function testItHandlesMissingAndInvalidPreviews(): void
    {
        self::assertSame(400, self::request('/email-preview')['status']);
        self::assertSame(400, self::request('/email-preview?id[]=bad')['status']);
        self::assertSame(404, self::request('/email-preview?id=missing')['status']);
        $identifier = 'email:one+two@example.test';
        self::assertSame(200, self::request('/email-preview?id=' . rawurlencode($identifier), [MessageViewerFixture::email($identifier)])['status']);
    }

    public function testItServesOnlyViewerAssets(): void
    {
        self::assertSame(200, self::request('/')['status']);
        self::assertSame(200, self::request('/viewer.js')['status']);
        self::assertSame(200, self::request('/viewer.css')['status']);
        self::assertSame(404, self::request('/../../.env')['status']);
        self::assertSame(404, self::request('/bin/dev/message-viewer.php')['status']);
    }

    public function testItKeepsSourceFailuresExplicit(): void
    {
        $response = route(MessageViewerFixture::request('/messages'), MessageViewerFixture::client([]), static function (): never {
            throw new RuntimeException('Start Docker.');
        }, 4566);
        self::assertSame(503, $response['status']);
        self::assertSame('{"error":"Start Docker."}', $response['body']);
    }

    public function testItValidatesTheLocalAwsPort(): void
    {
        self::assertSame(4566, awsPort('4566'));
        $this->expectException(RuntimeException::class);
        awsPort('65536');
    }

    public function testItRejectsInvalidSmsEncodingWithAStableError(): void
    {
        $response = route(MessageViewerFixture::request('/messages'), MessageViewerFixture::client([]), static fn (): array => [
            ['timestamp' => '2026-10-16T17:41:28Z', 'recipient' => '+905551112233', 'message' => "\xff"],
        ], 4566);
        self::assertSame(503, $response['status']);
        self::assertStringContainsString('invalid message encoding', $response['body']);
    }

    /**
     * @param list<array<string, mixed>> $emails
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private static function request(string $path, array $emails = []): array
    {
        return route(MessageViewerFixture::request($path), MessageViewerFixture::client($emails), static fn (): array => [], 4566);
    }
}
