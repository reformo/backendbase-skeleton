<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\NetgsmSmsNotifier;
use Backendbase\Infrastructure\Configuration\Notification\NetgsmSmsSettings;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use UnexpectedValueException;

use function base64_encode;
use function json_decode;

final class NetgsmSmsNotifierTest extends TestCase
{
    #[Test]
    #[DataProvider('phoneNumbers')]
    public function itMapsAnSmsToTheNetgsmJsonApi(string $phoneNumber, string $expected): void
    {
        $handler  = new MockHandler([
            static function (RequestInterface $request, array $options) use ($expected): Response {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('/sms/rest/v2/send', $request->getUri()->getPath());
                self::assertSame('https', $request->getUri()->getScheme());
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame(
                    'Basic ' . base64_encode('8501234567:test-password'),
                    $request->getHeaderLine('Authorization'),
                );
                self::assertSame(4.0, $options['timeout']);
                self::assertSame(4.0, $options['connect_timeout']);
                $body = json_decode((string) $request->getBody());
                self::assertSame('SENDER', $body->msgheader);
                self::assertSame('TR', $body->encoding);
                self::assertSame('Message', $body->messages[0]->msg);
                self::assertSame($expected, $body->messages[0]->no);

                return new Response(200, [], '{"code":"00","jobid":"17377215342605050417149344"}');
            },
        ]);
        $notifier = self::notifier($handler);

        $result = $notifier->notify(new SmsNotification($phoneNumber, 'Message'));

        self::assertSame('sms', $notifier->type());
        self::assertSame('17377215342605050417149344', $result->messageId('sms'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function phoneNumbers(): iterable
    {
        yield 'Turkish number' => ['+905551112233', '5551112233'];
        yield 'international number' => ['+4915112345678', '004915112345678'];
    }

    #[Test]
    public function itRejectsAnotherNotificationType(): void
    {
        $this->expectException(UnexpectedValueException::class);

        self::notifier(new MockHandler())->notify(new EmailNotification());
    }

    #[Test]
    public function itTranslatesTransportFailures(): void
    {
        $error = new ConnectException('Connection failed.', new Request('POST', 'https://api.netgsm.com.tr'));
        $this->expectException(NotificationProviderFailed::class);

        self::notifier(new MockHandler([$error]))->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    public function itTranslatesProviderRejection(): void
    {
        $this->expectException(NotificationProviderFailed::class);

        self::notifier(new MockHandler([new Response(200, [], '{"code":"30"}')]))
            ->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    public function itRejectsAnUnsuccessfulHttpResponse(): void
    {
        $this->expectException(NotificationProviderFailed::class);

        self::notifier(new MockHandler([new Response(406, [], '{}')]), false)
            ->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    #[DataProvider('invalidResponses')]
    public function itRejectsInvalidResponses(string $body): void
    {
        $this->expectException(UnexpectedValueException::class);

        self::notifier(new MockHandler([new Response(200, [], $body)]))
            ->notify(new SmsNotification('+905551112233', 'Message'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidResponses(): iterable
    {
        yield 'malformed JSON' => ['{'];
        yield 'non-object JSON' => ['[]'];
        yield 'missing code' => ['{}'];
        yield 'missing job ID' => ['{"code":"00"}'];
        yield 'empty job ID' => ['{"code":"00","jobid":""}'];
    }

    private static function notifier(MockHandler $handler, bool $httpErrors = true): NetgsmSmsNotifier
    {
        $settings = new NetgsmSmsSettings([
            'username' => '8501234567',
            'password' => 'test-password',
            'sender' => 'SENDER',
            'encoding' => 'TR',
            'timeoutSeconds' => 4.0,
        ]);

        return new NetgsmSmsNotifier(new Client(['handler' => $handler, 'http_errors' => $httpErrors]), $settings);
    }
}
