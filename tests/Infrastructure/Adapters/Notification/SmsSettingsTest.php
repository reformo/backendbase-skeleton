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

final class SmsSettingsTest extends TestCase
{
    private const string ACCOUNT_SID = 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[Test]
    public function itDefaultsToSns(): void
    {
        $settings = new NotificationSettings(new Settings([]));
        $sms      = $settings->sms();

        self::assertSame('sns', $sms->driver());
        $this->expectException(UnexpectedValueException::class);

        $sms->twilio();
    }

    #[Test]
    public function itExposesTwilioSettings(): void
    {
        $settings = self::smsSettings('twilio', ['twilio' => self::twilioValues()]);
        $twilio   = $settings->sms()->twilio();

        self::assertSame(self::ACCOUNT_SID, $twilio->accountSid());
        self::assertSame('token', $twilio->authToken());
        self::assertSame('+14155550100', $twilio->from());
        self::assertSame(4.0, $twilio->timeoutSeconds());
        $this->expectException(UnexpectedValueException::class);

        $settings->sms()->netgsm();
    }

    #[Test]
    public function itExposesNetgsmSettings(): void
    {
        $settings = self::smsSettings('netgsm', ['netgsm' => self::netgsmValues()]);
        $netgsm   = $settings->sms()->netgsm();

        self::assertSame('8501234567', $netgsm->username());
        self::assertSame('password', $netgsm->password());
        self::assertSame('SENDER', $netgsm->sender());
        self::assertSame('TR', $netgsm->encoding());
        self::assertSame(4.0, $netgsm->timeoutSeconds());
    }

    /** @param array<string, mixed> $values */
    #[Test]
    #[DataProvider('invalidSettings')]
    public function itRejectsInvalidSmsSettings(array $values): void
    {
        $this->expectException(UnexpectedValueException::class);

        new NotificationSettings(new Settings(['notification' => ['sms' => $values]]));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSettings(): iterable
    {
        yield 'unknown driver' => [['driver' => 'other']];
        yield 'missing Twilio settings' => [['driver' => 'twilio']];
        yield 'missing Netgsm settings' => [['driver' => 'netgsm']];
        yield 'invalid Twilio shape' => [['driver' => 'twilio', 'twilio' => 'invalid']];
        yield 'invalid Netgsm shape' => [['driver' => 'netgsm', 'netgsm' => 'invalid']];
        yield 'invalid Twilio account SID' => [['driver' => 'twilio', 'twilio' => ['accountSid' => 'bad']]];
        yield 'invalid Twilio token' => [['driver' => 'twilio', 'twilio' => [...self::twilioValues(), 'authToken' => '']]];
        yield 'invalid Twilio sender' => [['driver' => 'twilio', 'twilio' => [...self::twilioValues(), 'from' => '']]];
        yield 'invalid Twilio timeout' => [['driver' => 'twilio', 'twilio' => [...self::twilioValues(), 'timeoutSeconds' => INF]]];
        yield 'invalid Netgsm user' => [['driver' => 'netgsm', 'netgsm' => [...self::netgsmValues(), 'username' => '']]];
        yield 'invalid Netgsm password' => [['driver' => 'netgsm', 'netgsm' => [...self::netgsmValues(), 'password' => '']]];
        yield 'short Netgsm sender' => [['driver' => 'netgsm', 'netgsm' => [...self::netgsmValues(), 'sender' => 'AB']]];
        yield 'long Netgsm sender' => [['driver' => 'netgsm', 'netgsm' => [...self::netgsmValues(), 'sender' => 'ABCDEFGHIJKL']]];
        yield 'invalid Netgsm encoding' => [['driver' => 'netgsm', 'netgsm' => [...self::netgsmValues(), 'encoding' => 'other']]];
        yield 'invalid Netgsm timeout' => [['driver' => 'netgsm', 'netgsm' => [...self::netgsmValues(), 'timeoutSeconds' => 0]]];
    }

    #[Test]
    public function itRejectsNonArraySmsSettings(): void
    {
        $this->expectException(UnexpectedValueException::class);

        new NotificationSettings(new Settings(['notification' => ['sms' => 'invalid']]));
    }

    /** @param array<string, mixed> $values */
    private static function smsSettings(string $driver, array $values): NotificationSettings
    {
        return new NotificationSettings(new Settings(['notification' => ['sms' => ['driver' => $driver, ...$values]]]));
    }

    /** @return array<string, mixed> */
    private static function twilioValues(): array
    {
        return ['accountSid' => self::ACCOUNT_SID, 'authToken' => 'token', 'from' => '+14155550100', 'timeoutSeconds' => 4.0];
    }

    /** @return array<string, mixed> */
    private static function netgsmValues(): array
    {
        return ['username' => '8501234567', 'password' => 'password', 'sender' => 'SENDER', 'encoding' => 'TR', 'timeoutSeconds' => 4.0];
    }
}
