<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Aws\Sns\SnsClient;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Infrastructure\Configuration\Aws\SnsSettings;
use Backendbase\Infrastructure\Configuration\NotificationSettings;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\PushNotification;
use Backendbase\Shared\Services\Settings;
use DI\ContainerBuilder;
use Kreait\Firebase\Contract\Messaging;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Tests\Infrastructure\Composition\ProductionContainerFixture;

final class NotificationCompositionTest extends TestCase
{
    #[Test]
    public function itRegistersSesForDirectEmailDelivery(): void
    {
        $ses    = self::sesClient(new MockHandler([new Result(['MessageId' => 'ses-id'])]));
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $messaging = $this->createStub(Messaging::class);
        $notifier  = self::notifier([], $ses, $mailer, $messaging);

        self::assertInstanceOf(StackNotifier::class, $notifier);
        self::assertSame('ses-id', $notifier->notify(self::email())->messageId('email'));
    }

    #[Test]
    public function itDoesNotRetrySesAfterAnUnknownSendOutcome(): void
    {
        $container = ProductionContainerFixture::build([LoggerInterface::class => new NullLogger()]);
        $client    = $container->get(SesV2Client::class);
        $attempts  = 0;
        $transport = new MockHandler([
            static function (CommandInterface $command, RequestInterface $request) use (&$attempts): AwsException {
                ++$attempts;
                self::assertSame('SendEmail', $command->getName());

                return new AwsException('Connection failed after submission.', $command, [
                    'connection_error' => true,
                    'request' => $request,
                ]);
            },
            new Result(['MessageId' => 'duplicate-email']),
        ]);
        // Replace only the transport so the production SDK middleware remains in place.
        $client->getHandlerList()->setHandler($transport);
        $notifier = $container->get(Notify::class);

        try {
            $notifier->notify(self::email());
            self::fail('An unknown SES outcome must reach the caller.');
        } catch (NotificationProviderFailed $exception) {
            $cause = $exception->getPrevious();
            self::assertInstanceOf(AwsException::class, $cause);
            self::assertTrue($cause->isConnectionError());
        }

        self::assertSame(1, $attempts);
        self::assertCount(1, $transport);
    }

    #[Test]
    public function itRegistersSmtpAndConfiguredPushProviders(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');
        $messaging = $this->createMock(Messaging::class);
        $messaging->expects(self::once())->method('send')->willReturn(['name' => 'push-id']);
        $settings = [
            'email' => [
                'driver' => 'smtp',
                'smtp' => [
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'username' => '',
                    'password' => '',
                    'encryption' => 'starttls',
                    'timeoutSeconds' => 5.0,
                ],
            ],
            'push' => ['projectId' => 'test-project'],
        ];
        $notifier = self::notifier($settings, self::sesClient(new MockHandler()), $mailer, $messaging);
        $push     = new PushNotification();
        $push->setDeviceToken('device-token')->setBody('Message');

        self::assertTrue($notifier->notify(self::email())->has('email'));
        self::assertSame('push-id', $notifier->notify($push)->messageId('push'));
    }

    /** @param array<string, mixed> $notificationSettings */
    private static function notifier(
        array $notificationSettings,
        SesV2Client $ses,
        MailerInterface $mailer,
        Messaging $messaging,
    ): Notify {
        $builder     = new ContainerBuilder();
        $definitions = require 'config/dependencies/notification.php';
        $definitions($builder);
        $settings = new NotificationSettings(new Settings(['notification' => $notificationSettings]));
        $sns      = new SnsNotifier(self::snsClient(), new SnsSettings('Transactional', null));
        $builder->addDefinitions([
            NotificationSettings::class => $settings,
            LoggerInterface::class => new NullLogger(),
            SnsNotifier::class => $sns,
            SesV2Client::class => $ses,
            MailerInterface::class => $mailer,
            Messaging::class => $messaging,
        ]);

        return $builder->build()->get(Notify::class);
    }

    private static function email(): EmailNotification
    {
        $email = new EmailNotification();
        $email->setFromAddress(Address::create('from@example.com'));
        $email->addToAddress(Address::create('to@example.com'));
        $email->setSubject('Subject')->setHtmlBody('<p>Message</p>');

        return $email;
    }

    private static function sesClient(MockHandler $handler): SesV2Client
    {
        return new SesV2Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }

    private static function snsClient(): SnsClient
    {
        return new SnsClient([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => new MockHandler(),
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }
}
