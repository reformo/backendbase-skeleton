<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Aws\MockHandler as AwsMockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Aws\Sns\SnsClient;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Infrastructure\Configuration\Aws\SnsSettings;
use Backendbase\Infrastructure\Configuration\NotificationSettings;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Backendbase\Shared\Services\Settings;
use DI\ContainerBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SmsCompositionTest extends TestCase
{
    #[Test]
    #[DataProvider('drivers')]
    public function itRegistersTheSelectedSmsProvider(string $driver, string $response, string $messageId): void
    {
        $settings    = new NotificationSettings(new Settings([
            'notification' => [
                'sms' => [
                    'driver' => $driver,
                    'twilio' => [
                        'accountSid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                        'authToken' => 'token',
                        'from' => '+14155550100',
                    ],
                    'netgsm' => [
                        'username' => '8501234567',
                        'password' => 'password',
                        'sender' => 'SENDER',
                    ],
                ],
            ],
        ]));
        $builder     = new ContainerBuilder();
        $definitions = require 'config/dependencies/notification.php';
        $definitions($builder);
        $builder->addDefinitions([
            NotificationSettings::class => $settings,
            LoggerInterface::class => new NullLogger(),
            ClientInterface::class => new Client(['handler' => new MockHandler([new Response(200, [], $response)])]),
            SnsNotifier::class => self::snsNotifier(),
            SesV2Client::class => self::sesClient(),
        ]);
        $notifier = $builder->build()->get(Notify::class);

        self::assertSame($messageId, $notifier->notify(new SmsNotification('+905551112233', 'Message'))->messageId('sms'));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function drivers(): iterable
    {
        yield 'Twilio' => ['twilio', '{"sid":"SMbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"}', 'SMbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'];
        yield 'Netgsm' => ['netgsm', '{"code":"00","jobid":"netgsm-job"}', 'netgsm-job'];
    }

    #[Test]
    public function itKeepsSnsAsTheDefaultSmsProvider(): void
    {
        $settings    = new NotificationSettings(new Settings([]));
        $builder     = new ContainerBuilder();
        $definitions = require 'config/dependencies/notification.php';
        $definitions($builder);
        $builder->addDefinitions([
            NotificationSettings::class => $settings,
            LoggerInterface::class => new NullLogger(),
            SnsNotifier::class => self::snsNotifier(new AwsMockHandler([new Result(['MessageId' => 'sns-id'])])),
            SesV2Client::class => self::sesClient(),
        ]);
        $notifier = $builder->build()->get(Notify::class);

        self::assertSame('sns-id', $notifier->notify(new SmsNotification('+905551112233', 'Message'))->messageId('sms'));
    }

    private static function snsNotifier(AwsMockHandler|null $handler = null): SnsNotifier
    {
        $client = new SnsClient([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler ?? new AwsMockHandler(),
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);

        return new SnsNotifier($client, new SnsSettings('Transactional', null));
    }

    private static function sesClient(): SesV2Client
    {
        return new SesV2Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => new AwsMockHandler(),
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }
}
