<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\PushNotification;
use InvalidArgumentException;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Override;
use UnexpectedValueException;

use function array_replace_recursive;
use function filter_var;
use function is_string;
use function str_starts_with;

use const FILTER_VALIDATE_URL;

final readonly class FirebasePushNotifier implements NotificationProvider
{
    public const string TYPE = 'push';

    public function __construct(private Messaging $client, private string $cdnBaseUrl = '')
    {
    }

    #[Override]
    public function type(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function notify(Notification $notification): NotificationResult
    {
        if (! $notification instanceof PushNotification) {
            throw new UnexpectedValueException('Firebase requires a push notification.');
        }

        $message = $this->message($notification);
        $client  = $this->client;
        try {
            $result = $client->send($message);
        } catch (MessagingException $exception) {
            throw new NotificationProviderFailed(self::TYPE, $exception);
        }

        $messageId = $result['name'] ?? null;
        if (! is_string($messageId) || $messageId === '') {
            throw new UnexpectedValueException('Firebase returned no message identifier.');
        }

        return NotificationResult::delivered(self::TYPE, $messageId);
    }

    private function message(PushNotification $notification): CloudMessage
    {
        $target      = $notification->target();
        $message     = CloudMessage::new()->withNotification(FirebaseNotification::create(
            $notification->title(),
            $notification->body(),
        ));
        $targetType  = $target->type();
        $targetValue = $target->value();
        $message     = $targetType === 'topic'
            ? $message->withTopic($targetValue)
            : $message->withToken($targetValue);
        $data        = $notification->stringData();
        $message     = $message->withData($data);

        return $this->withPlatformOptions($message, $notification);
    }

    private function withPlatformOptions(CloudMessage $message, PushNotification $notification): CloudMessage
    {
        $options = $notification->platformOptions();
        $android = $options->android();
        $apns    = $options->apns();
        $image   = $notification->notificationImage();
        if ($image !== null) {
            $imageUrl = $this->imageUrl($image);
            $android  = array_replace_recursive($android, ['notification' => ['image' => $imageUrl]]);
            $apns     = array_replace_recursive($apns, [
                'payload' => ['aps' => ['mutable-content' => 1]],
                'fcm_options' => ['image' => $imageUrl],
            ]);
        }

        if ($android !== []) {
            $message = $message->withAndroidConfig($android);
        }

        if ($apns !== []) {
            $message = $message->withApnsConfig($apns);
        }

        return $message;
    }

    private function imageUrl(string $image): string
    {
        $absolute = str_starts_with($image, 'https://') || str_starts_with($image, 'http://');
        $url      = $absolute ? $image : $this->cdnBaseUrl . $image;
        $httpUrl  = str_starts_with($url, 'https://') || str_starts_with($url, 'http://');
        if (! $httpUrl || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('A push image requires an absolute URL or CDN base URL.');
        }

        return $url;
    }
}
