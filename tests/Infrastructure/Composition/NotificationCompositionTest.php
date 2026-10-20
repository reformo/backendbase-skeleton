<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Aws\Sns\SnsClient;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\PushNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Kreait\Firebase\Contract\Messaging;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use UnexpectedValueException;

use function str_repeat;

final class NotificationCompositionTest extends TestCase
{
    #[DataProvider('branches')]
    public function testProductionFactoriesDispatchToTheSelectedProviders(string $sms, string $email, bool $push): void
    {
        $client    = $this->createMock(ClientInterface::class);
        $smsResult = $sms === 'twilio' ? 'SM' . str_repeat('a', 32) : $sms;
        $response  = $sms === 'twilio' ? '{"sid":"' . $smsResult . '","status":"queued"}' : '{"code":"00","jobid":"netgsm"}';
        $client->expects($sms === 'sns' ? self::never() : self::once())->method('request')->willReturn(new Response(200, [], $response));
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($email === 'smtp' ? self::once() : self::never())->method('send');
        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($push ? self::once() : self::never())->method('send')->willReturn(['name' => 'push']);
        $container = ProductionContainerFixture::configured([
            'notification' => [
                'sms' => ['driver' => $sms, 'twilio' => ['account-sid' => 'AC' . str_repeat('a', 32), 'auth-token' => 'fixture', 'from' => '+905551234567'], 'netgsm' => ['username' => 'fixture', 'password' => 'fixture', 'sender' => 'Fixture']],
                'email' => ['driver' => $email, 'smtp' => ['host' => '127.0.0.1']],
                'push' => ['project-id' => $push ? 'fixture' : '', 'credentials-path' => ''],
            ],
        ], [
            ClientInterface::class => $client,
            MailerInterface::class => $mailer,
            Messaging::class => $messaging,
            LoggerInterface::class => new NullLogger(),
            SnsClient::class => new SnsClient(self::aws('sns')),
            SesV2Client::class => new SesV2Client(self::aws('ses')),
        ]);
        $notifier  = $container->get(Notify::class);
        $result    = $notifier->notify(new SmsNotification('+905551234568', 'Composition test'));
        self::assertSame($smsResult, $result->messageId('sms'));
        $result = $notifier->notify(self::email());
        self::assertTrue($result->has('email'));
        self::assertSame($email === 'ses' ? 'ses' : null, $result->messageId('email'));
        $notification = new PushNotification();
        $notification->setTopic('fixture');
        $notification->setTitle('Composition test');
        $notification->setBody('Fixture');
        if (! $push) {
            $this->expectException(UnexpectedValueException::class);
            $this->expectExceptionMessage('No notification provider is registered for push.');
        }

        $result = $notifier->notify($notification);
        self::assertSame('push', $result->messageId('push'));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function branches(): iterable
    {
        yield 'SNS SES without push' => ['sns', 'ses', false];
        yield 'SNS SES with push' => ['sns', 'ses', true];
        yield 'SNS SMTP without push' => ['sns', 'smtp', false];
        yield 'SNS SMTP with push' => ['sns', 'smtp', true];
        yield 'Twilio SES without push' => ['twilio', 'ses', false];
        yield 'Twilio SES with push' => ['twilio', 'ses', true];
        yield 'Twilio SMTP without push' => ['twilio', 'smtp', false];
        yield 'Twilio SMTP with push' => ['twilio', 'smtp', true];
        yield 'Netgsm SES without push' => ['netgsm', 'ses', false];
        yield 'Netgsm SES with push' => ['netgsm', 'ses', true];
        yield 'Netgsm SMTP without push' => ['netgsm', 'smtp', false];
        yield 'Netgsm SMTP with push' => ['netgsm', 'smtp', true];
    }

    /** @return array<string, mixed> */
    private static function aws(string $messageId): array
    {
        return ['credentials' => ['key' => 'fixture', 'secret' => 'fixture'], 'region' => 'eu-central-1', 'version' => 'latest', 'handler' => new MockHandler([new Result(['MessageId' => $messageId])])];
    }

    private static function email(): EmailNotification
    {
        $notification = new EmailNotification();
        $notification->setFromAddress(Address::create('fixture@example.com'));
        $notification->addToAddress(Address::create('recipient@example.com'));
        $notification->setSubject('Composition test');
        $notification->setHtmlBody('Fixture');

        return $notification;
    }
}
