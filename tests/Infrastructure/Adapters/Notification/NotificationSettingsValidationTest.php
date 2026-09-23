<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Configuration\NotificationSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use const INF;

final class NotificationSettingsValidationTest extends TestCase
{
    #[Test]
    public function itRejectsAnUnknownEmailDriver(): void
    {
        $settings = new Settings(['notification' => ['email' => ['driver' => 'unknown']]]);

        $this->expectException(UnexpectedValueException::class);

        new NotificationSettings($settings);
    }

    #[Test]
    public function itRejectsAnInvalidNotificationSettingsShape(): void
    {
        $this->expectException(UnexpectedValueException::class);

        new NotificationSettings(new Settings(['notification' => 'invalid']));
    }

    #[Test]
    public function itRequiresAnSmtpHost(): void
    {
        $settings = new Settings(['notification' => ['email' => ['driver' => 'smtp']]]);

        $this->expectException(UnexpectedValueException::class);

        new NotificationSettings($settings);
    }

    #[Test]
    public function itRejectsSmtpSettingsWhenSesIsSelected(): void
    {
        $settings = new NotificationSettings(new Settings([]));
        $email    = $settings->email();

        $this->expectException(UnexpectedValueException::class);

        $email->smtp();
    }

    /** @param array<string, mixed> $settings */
    #[Test]
    #[DataProvider('invalidSettings')]
    public function itRejectsInvalidNotificationSettings(array $settings): void
    {
        $this->expectException(UnexpectedValueException::class);

        new NotificationSettings(new Settings(['notification' => $settings]));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSettings(): iterable
    {
        yield 'email shape' => [['email' => 'invalid']];
        yield 'SMTP shape' => [['email' => ['driver' => 'smtp', 'smtp' => 'invalid']]];
        yield 'SMTP credentials' => [
            [
                'email' => [
                    'driver' => 'smtp',
                    'smtp' => [
                        'host' => 'smtp.example.com',
                        'username' => 'user',
                    ],
                ],
            ],
        ];

        yield 'SMTP encryption' => [
            [
                'email' => [
                    'driver' => 'smtp',
                    'smtp' => [
                        'host' => 'smtp.example.com',
                        'encryption' => 'invalid',
                    ],
                ],
            ],
        ];

        yield 'SMTP timeout' => [
            [
                'email' => [
                    'driver' => 'smtp',
                    'smtp' => [
                        'host' => 'smtp.example.com',
                        'timeoutSeconds' => 0,
                    ],
                ],
            ],
        ];

        yield 'push shape' => [['push' => 'invalid']];
        yield 'push field' => [['push' => ['projectId' => 123]]];
        yield 'push project ID' => [['push' => ['projectId' => 'Invalid ID']]];
        yield 'push timeout' => [['push' => ['timeoutSeconds' => 0]]];
        yield 'push infinite timeout' => [['push' => ['timeoutSeconds' => INF]]];
        yield 'SMTP infinite timeout' => [
            [
                'email' => [
                    'driver' => 'smtp',
                    'smtp' => [
                        'host' => 'smtp.example.com',
                        'timeoutSeconds' => INF,
                    ],
                ],
            ],
        ];
    }
}
