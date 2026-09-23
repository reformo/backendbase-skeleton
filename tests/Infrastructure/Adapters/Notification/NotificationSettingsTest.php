<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Configuration\NotificationSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationSettingsTest extends TestCase
{
    #[Test]
    public function itUsesSesWhenNoEmailDriverIsSelected(): void
    {
        $settings = new NotificationSettings(new Settings([]));

        self::assertSame('ses', $settings->email()->driver());
        self::assertFalse($settings->push()->enabled());
    }

    #[Test]
    public function itExposesValidatedSmtpAndPushValues(): void
    {
        $settings = new NotificationSettings(new Settings([
            'notification' => [
                'email' => [
                    'driver' => 'smtp',
                    'smtp' => [
                        'host' => 'smtp.example.com',
                        'port' => 465,
                        'username' => 'user',
                        'password' => 'secret',
                        'encryption' => 'smtps',
                        'timeoutSeconds' => 4.0,
                    ],
                ],
                'push' => [
                    'projectId' => 'project',
                    'credentialsPath' => '/tmp/service-account.json',
                    'cdnBaseUrl' => 'https://cdn.example.com/',
                    'timeoutSeconds' => 3.0,
                ],
            ],
        ]));
        $email    = $settings->email();
        $smtp     = $email->smtp();
        $push     = $settings->push();

        self::assertSame('smtp.example.com', $smtp->host());
        self::assertSame(465, $smtp->port());
        self::assertSame('user', $smtp->username());
        self::assertSame('secret', $smtp->password());
        self::assertSame('smtps', $smtp->encryption());
        self::assertSame(4.0, $smtp->timeoutSeconds());
        self::assertTrue($push->enabled());
        self::assertSame('project', $push->projectId());
        self::assertSame('/tmp/service-account.json', $push->credentialsPath());
        self::assertSame('https://cdn.example.com/', $push->cdnBaseUrl());
        self::assertSame(3.0, $push->timeoutSeconds());
    }
}
