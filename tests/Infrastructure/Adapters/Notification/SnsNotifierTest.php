<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sns\SnsClient;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class SnsNotifierTest extends TestCase
{
    #[Test]
    public function itPublishesAnSmsNotification(): void
    {
        $handler  = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame('Publish', $command->getName());
                self::assertSame('+905551112233', $command['PhoneNumber']);
                self::assertSame('Your verification code is 123456.', $command['Message']);
                self::assertSame(
                    'Transactional',
                    $command['MessageAttributes']['AWS.SNS.SMS.SMSType']['StringValue'],
                );
                self::assertSame(
                    'Backendbase',
                    $command['MessageAttributes']['AWS.SNS.SMS.SenderID']['StringValue'],
                );

                return new Result(['MessageId' => 'sns-message-1']);
            },
        ]);
        $notifier = new SnsNotifier(
            self::snsClient($handler),
            ['smsType' => 'Transactional', 'senderId' => 'Backendbase'],
        );

        $result = $notifier->notify(
            new SmsNotification('+905551112233', 'Your verification code is 123456.'),
        );

        self::assertSame('sns-message-1', $result->messageId('sms'));
        self::assertSame('sms', $notifier->type());
    }

    #[Test]
    public function itOmitsAnEmptySenderIdentifier(): void
    {
        $handler  = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertArrayNotHasKey(
                    'AWS.SNS.SMS.SenderID',
                    $command['MessageAttributes'],
                );

                return new Result(['MessageId' => 'sns-message-2']);
            },
        ]);
        $notifier = new SnsNotifier(self::snsClient($handler), ['senderId' => '']);

        self::assertSame(
            'sns-message-2',
            $notifier->notify(new SmsNotification('+905551112233', 'Message'))->messageId('sms'),
        );
    }

    #[Test]
    public function itRejectsAnUnsupportedNotification(): void
    {
        $notifier = new SnsNotifier(self::snsClient(new MockHandler()), []);

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify(new EmailNotification());
    }

    #[Test]
    public function itRejectsAnInvalidSmsType(): void
    {
        $notifier = new SnsNotifier(
            self::snsClient(new MockHandler()),
            ['smsType' => 'invalid'],
        );

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify(new SmsNotification('+905551112233', 'Message'));
    }

    private static function snsClient(MockHandler $handler): SnsClient
    {
        return new SnsClient([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }
}
