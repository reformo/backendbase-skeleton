<?php

declare(strict_types=1);

use Aws\SesV2\SesV2Client;
use Backendbase\Infrastructure\Adapters\Notification\EmailMessageFactory;
use Backendbase\Infrastructure\Adapters\Notification\FirebasePushNotifier;
use Backendbase\Infrastructure\Adapters\Notification\NetgsmSmsNotifier;
use Backendbase\Infrastructure\Adapters\Notification\SesEmailNotifier;
use Backendbase\Infrastructure\Adapters\Notification\SmtpEmailNotifier;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Infrastructure\Adapters\Notification\TwilioSmsNotifier;
use Backendbase\Infrastructure\Configuration\NotificationSettings;
use Backendbase\Shared\Integrations\Notify;
use DI\ContainerBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        ClientInterface::class => static fn (): ClientInterface => new Client(),
        TwilioSmsNotifier::class => static function (ContainerInterface $container) {
            $settings = $container->get(NotificationSettings::class);
            $sms      = $settings->sms();
            $twilio   = $sms->twilio();
            $client   = $container->get(ClientInterface::class);

            return new TwilioSmsNotifier($client, $twilio);
        },
        NetgsmSmsNotifier::class => static function (ContainerInterface $container) {
            $settings = $container->get(NotificationSettings::class);
            $sms      = $settings->sms();
            $netgsm   = $sms->netgsm();
            $client   = $container->get(ClientInterface::class);

            return new NetgsmSmsNotifier($client, $netgsm);
        },
        Messaging::class => static function (ContainerInterface $container) {
            $settings        = $container->get(NotificationSettings::class);
            $push            = $settings->push();
            $timeout         = $push->timeoutSeconds();
            $options         = HttpClientOptions::default();
            $options         = $options->withConnectTimeout($timeout);
            $options         = $options->withTimeout($timeout);
            $factory         = new Factory();
            $projectId       = $push->projectId();
            $factory         = $factory->withProjectId($projectId);
            $factory         = $factory->withHttpClientOptions($options);
            $credentialsPath = $push->credentialsPath();
            if ($credentialsPath !== '') {
                $factory = $factory->withServiceAccount($credentialsPath);
            }

            return $factory->createMessaging();
        },
        FirebasePushNotifier::class => static function (ContainerInterface $container) {
            $settings   = $container->get(NotificationSettings::class);
            $push       = $settings->push();
            $client     = $container->get(Messaging::class);
            $cdnBaseUrl = $push->cdnBaseUrl();

            return new FirebasePushNotifier($client, $cdnBaseUrl);
        },
        MailerInterface::class => static function (ContainerInterface $container) {
            $settings   = $container->get(NotificationSettings::class);
            $email      = $settings->email();
            $smtp       = $email->smtp();
            $encryption = $smtp->encryption();
            $stream     = new SocketStream();
            $timeout    = $smtp->timeoutSeconds();
            $stream->setTimeout($timeout);
            $host      = $smtp->host();
            $port      = $smtp->port();
            $transport = new EsmtpTransport($host, $port, $encryption === 'smtps', stream: $stream);
            $transport->setAutoTls($encryption === 'starttls');
            $transport->setRequireTls($encryption === 'starttls');
            $username = $smtp->username();
            if ($username !== '') {
                $transport->setUsername($username);
                $password = $smtp->password();
                $transport->setPassword($password);
            }

            return new Mailer($transport);
        },
        SesEmailNotifier::class => static function (ContainerInterface $container) {
            $client  = $container->get(SesV2Client::class);
            $factory = $container->get(EmailMessageFactory::class);

            return new SesEmailNotifier($client, $factory);
        },
        SmtpEmailNotifier::class => static function (ContainerInterface $container) {
            $mailer  = $container->get(MailerInterface::class);
            $factory = $container->get(EmailMessageFactory::class);

            return new SmtpEmailNotifier($mailer, $factory);
        },
        Notify::class => static function (ContainerInterface $container) {
            $logger           = $container->get(LoggerInterface::class);
            $notifier         = new StackNotifier($logger);
            $settings         = $container->get(NotificationSettings::class);
            $sms              = $settings->sms();
            $smsProviderClass = match ($sms->driver()) {
                'twilio' => TwilioSmsNotifier::class,
                'netgsm' => NetgsmSmsNotifier::class,
                default => SnsNotifier::class,
            };
            $smsProvider = $container->get($smsProviderClass);
            $notifier->add($smsProvider);
            $email                 = $settings->email();
            $emailProvider         = $email->driver() === 'ses' ? SesEmailNotifier::class : SmtpEmailNotifier::class;
            $selectedEmailProvider = $container->get($emailProvider);
            $notifier->add($selectedEmailProvider);
            $push = $settings->push();
            if ($push->enabled()) {
                $pushProvider = $container->get(FirebasePushNotifier::class);
                $notifier->add($pushProvider);
            }

            return $notifier;
        },
    ]);
};
