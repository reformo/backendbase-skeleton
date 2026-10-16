<?php

declare(strict_types=1);

namespace Tests\Tools;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_fill;
use function Backendbase\MessageViewer\parseEmails;
use function Backendbase\MessageViewer\readEmails;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

require_once 'bin/dev/message-viewer/emails.php';

final class MessageViewerEmailsTest extends TestCase
{
    public function testItReadsStoredEmailHeadersBodiesAndMissingRawRecipients(): void
    {
        $email        = MessageViewerFixture::email();
        $email['To']  = [];
        $email['CC']  = ['copy@example.test'];
        $email['BCC'] = ['blind@example.test'];
        $messages     = readEmails(MessageViewerFixture::client([$email]), 4566);
        self::assertSame([], $messages[0]['to']);
        self::assertSame(['copy@example.test'], $messages[0]['cc']);
        self::assertSame(['blind@example.test'], $messages[0]['bcc']);
        self::assertSame($email['Source'], $messages[0]['sender']);
        self::assertSame($email['Subject'], $messages[0]['subject']);
        self::assertSame($email['BodyHtml'], $messages[0]['html']);
        self::assertSame($email['BodyText'], $messages[0]['text']);
        self::assertSame('2026-10-16T17:11:30.000000Z', $messages[0]['timestamp']);
    }

    public function testItSortsAcrossAccountsAndBoundsTheList(): void
    {
        $payload  = [
            'messages' => [
                'first' => array_fill(0, 201, MessageViewerFixture::email('older', 100)),
                'second' => [MessageViewerFixture::email('newer', 200)],
            ],
        ];
        $messages = parseEmails(json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertCount(200, $messages);
        self::assertSame('newer', $messages[0]['id']);
        self::assertSame([], parseEmails('{"messages":{}}'));
    }

    public function testItSupportsPlainTextOnlyEmails(): void
    {
        $email             = MessageViewerFixture::email();
        $email['BodyHtml'] = null;
        self::assertSame('', readEmails(MessageViewerFixture::client([$email]), 4566)[0]['html']);
    }

    #[DataProvider('invalidPayloads')]
    public function testItRejectsMalformedResponses(string $payload): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MiniStack returned');
        parseEmails($payload);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'invalid JSON' => ['not JSON'];
        yield 'missing messages' => ['{}'];
        yield 'wrong account collection' => ['{"messages":{"account":null}}'];
        yield 'invalid record' => ['{"messages":{"account":[42]}}'];
        yield 'missing fields' => ['{"messages":{"account":[{}]}}'];

        foreach (['To' => 'wrong', 'CC' => [42], 'Subject' => false, 'Timestamp' => 'today', 'MessageId' => ''] as $field => $value) {
            $email         = MessageViewerFixture::email();
            $email[$field] = $value;

            yield $field => [json_encode(['messages' => ['account' => [$email]]], JSON_THROW_ON_ERROR)];
        }
    }

    public function testItPreservesSubsecondOrder(): void
    {
        $older              = MessageViewerFixture::email('older');
        $older['Timestamp'] = 1_792_170_690.1;
        $newer              = MessageViewerFixture::email('newer');
        $newer['Timestamp'] = 1_792_170_690.9;
        $messages           = readEmails(MessageViewerFixture::client([$older, $newer]), 4566);
        self::assertSame('newer', $messages[0]['id']);
        self::assertSame('2026-10-16T17:11:30.900000Z', $messages[0]['timestamp']);
    }

    public function testItBoundsReadsAndDisablesRedirectsAndProxies(): void
    {
        $handler = new MockHandler([new Response(200, [], '{"messages":{}}')]);
        $client  = new Client(['handler' => HandlerStack::create($handler)]);
        self::assertSame([], readEmails($client, 4566));
        $request = $handler->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://127.0.0.1:4566/_ministack/ses/messages', (string) $request->getUri());
        $options = $handler->getLastOptions();
        self::assertSame(5, $options['timeout']);
        self::assertFalse($options['allow_redirects']);
        self::assertSame('', $options['proxy']);
    }

    public function testItReportsConnectionFailureWithoutPrivateDetails(): void
    {
        $failure = new ConnectException('private diagnostic', new Request('GET', 'http://127.0.0.1'));
        $client  = new Client(['handler' => HandlerStack::create(new MockHandler([$failure]))]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read captured emails.');
        readEmails($client, 4566);
    }

    public function testItRejectsOversizedEmailResponses(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], str_repeat('x', 8_388_609))]))]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('8 MiB');
        readEmails($client, 4566);
    }
}
