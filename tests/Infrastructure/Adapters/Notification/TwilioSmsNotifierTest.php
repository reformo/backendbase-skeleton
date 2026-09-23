<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Adapters\Notification\TwilioSmsNotifier;
use Backendbase\Infrastructure\Configuration\Notification\TwilioSmsSettings;
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
use function parse_str;

final class TwilioSmsNotifierTest extends TestCase
{
    private const string ACCOUNT_SID = 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MESSAGE_SID = 'SMbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    #[Test]
    public function itMapsAnSmsToTheTwilioMessageApi(): void
    {
        $handler  = new MockHandler([
            static function (RequestInterface $request, array $options): Response {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('/2010-04-01/Accounts/' . self::ACCOUNT_SID . '/Messages.json', $request->getUri()->getPath());
                self::assertSame('https', $request->getUri()->getScheme());
                self::assertSame(4.0, $options['timeout']);
                self::assertSame(4.0, $options['connect_timeout']);
                self::assertSame(
                    'Basic ' . base64_encode(self::ACCOUNT_SID . ':test-token'),
                    $request->getHeaderLine('Authorization'),
                );
                parse_str((string) $request->getBody(), $form);
                self::assertSame('+905551112233', $form['To']);
                self::assertSame('+14155550100', $form['From']);
                self::assertSame('Message', $form['Body']);

                return new Response(201, [], '{"sid":"' . self::MESSAGE_SID . '","status":"queued"}');
            },
        ]);
        $notifier = self::notifier($handler);

        $result = $notifier->notify(new SmsNotification('+905551112233', 'Message'));

        self::assertSame('sms', $notifier->type());
        self::assertSame(self::MESSAGE_SID, $result->messageId('sms'));
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
        $error = new ConnectException('Connection failed.', new Request('POST', 'https://api.twilio.com'));
        $this->expectException(NotificationProviderFailed::class);

        self::notifier(new MockHandler([$error]))->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    public function itRejectsAnUnsuccessfulHttpResponse(): void
    {
        $this->expectException(NotificationProviderFailed::class);

        self::notifier(new MockHandler([new Response(400, [], '{}')]), false)
            ->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    public function itRejectsAFailedMessageStatus(): void
    {
        $this->expectException(NotificationProviderFailed::class);

        self::notifier(new MockHandler([new Response(201, [], '{"sid":"' . self::MESSAGE_SID . '","status":"failed"}')]))
            ->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    #[DataProvider('invalidResponses')]
    public function itRejectsInvalidResponses(string $body): void
    {
        $this->expectException(UnexpectedValueException::class);

        self::notifier(new MockHandler([new Response(201, [], $body)]))
            ->notify(new SmsNotification('+905551112233', 'Message'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidResponses(): iterable
    {
        yield 'malformed JSON' => ['{'];
        yield 'non-object JSON' => ['[]'];
        yield 'missing SID' => ['{}'];
        yield 'invalid SID' => ['{"sid":"wrong"}'];
    }

    private static function notifier(MockHandler $handler, bool $httpErrors = true): TwilioSmsNotifier
    {
        $settings = new TwilioSmsSettings([
            'accountSid' => self::ACCOUNT_SID,
            'authToken' => 'test-token',
            'from' => '+14155550100',
            'timeoutSeconds' => 4.0,
        ]);

        return new TwilioSmsNotifier(new Client(['handler' => $handler, 'http_errors' => $httpErrors]), $settings);
    }
}
