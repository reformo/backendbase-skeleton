<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\PushNotification;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Override;
use Psr\Log\LoggerInterface;

use function array_key_exists;
use function array_replace_recursive;

class FirebasePushNotifier implements Notify
{
    public const string TYPE = 'push';

    public function __construct(
        private readonly Messaging $client,
        private readonly LoggerInterface $logger,
        private readonly string $cdnBaseUrl = '',
    ) {
    }

    public function type(): string
    {
        return self::TYPE;
    }

    /**
     * @param PushNotification $params
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function notify(Notification $params): array
    {
        $params  = $params->toArray();
        $payload = [
            'message' => [
                'notification' => [
                    'title' => $params['title'] ?? null,
                    'body' => $params['body'],
                ],
            ],
        ];

        if (array_key_exists('topic', $params)) {
            $payload['message']['topic'] = $params['topic'];
        }

        if (array_key_exists('deviceToken', $params)) {
            $payload['message']['token'] = $params['deviceToken'];
        }

        if (array_key_exists('data', $params)) {
            foreach ($params['data'] as $key => $value) {
                $params['data'][$key] = (string) $params['data'][$key];
            }

            $payload['message']['data'] = $params['data'];
        }

        if (array_key_exists('data', $params) && array_key_exists('notificationImage', $params['data'])) {
            $payload['message']['android'] = [
                'notification' => ['image' => $this->cdnBaseUrl . $params['data']['notificationImage']],
            ];
            $payload['message']['apns']    = [
                'payload' => [
                    'aps' => ['mutable-content' => 1],
                ],
                'fcm_options' => [
                    'image' => $this->cdnBaseUrl . $params['data']['notificationImage'],
                ],
            ];
        }

        if (array_key_exists('android', $params)) {
            $payload['message']['android'] = array_replace_recursive(
                $payload['message']['android'] ?? [],
                $params['android'],
            );
        }

        if (array_key_exists('apns', $params)) {
            $payload['message']['apns'] = array_replace_recursive(
                $payload['message']['apns'] ?? [],
                $params['apns'],
            );
        }

        $this->logger->debug('motification message', $payload);

        $message = CloudMessage::fromArray($payload['message']);

        return $this->client->send($message);
    }

    #[Override]
    public function getClient(): mixed
    {
        return $this->client;
    }
}
