<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Aws\Sns\SnsClient;
use Backendbase\Infrastructure\Adapters\Notification\EmailMessageFactory;
use Backendbase\Infrastructure\Adapters\Notification\FirebasePushNotifier;
use Backendbase\Infrastructure\Adapters\Notification\SesEmailNotifier;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Infrastructure\Configuration\Aws\SnsSettings;
use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\PushNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Kreait\Firebase\Contract\Messaging;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ProviderResponseValidationTest extends TestCase
{
    #[Test]
    public function itRejectsAnSnsResponseWithoutAMessageIdentifier(): void
    {
        $client   = new SnsClient(self::awsConfiguration(new MockHandler([new Result([])])));
        $notifier = new SnsNotifier($client, new SnsSettings('Transactional', null));

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    public function itRejectsAnSesResponseWithoutAMessageIdentifier(): void
    {
        $client   = new SesV2Client(self::awsConfiguration(new MockHandler([new Result([])])));
        $notifier = new SesEmailNotifier($client, new EmailMessageFactory());

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify(self::email());
    }

    #[Test]
    public function itRejectsAnUnsupportedSesNotification(): void
    {
        $client   = new SesV2Client(self::awsConfiguration(new MockHandler()));
        $notifier = new SesEmailNotifier($client, new EmailMessageFactory());

        $this->expectException(UnexpectedValueException::class);

        $notifier->notify(new SmsNotification('+905551112233', 'Message'));
    }

    #[Test]
    public function itRejectsAFirebaseResponseWithoutAMessageIdentifier(): void
    {
        $client = $this->createStub(Messaging::class);
        $client->method('send')->willReturn([]);
        $notification = new PushNotification();
        $notification->setTopic('updates')->setBody('Message');

        $this->expectException(UnexpectedValueException::class);

        new FirebasePushNotifier($client)->notify($notification);
    }

    /** @return array<string, mixed> */
    private static function awsConfiguration(MockHandler $handler): array
    {
        return [
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'version' => 'latest',
        ];
    }

    private static function email(): EmailNotification
    {
        $email = new EmailNotification();
        $email->setFromAddress(Address::create('from@example.com'));
        $email->addToAddress(Address::create('to@example.com'));
        $email->setSubject('Subject')->setHtmlBody('<p>Body</p>');

        return $email;
    }
}
