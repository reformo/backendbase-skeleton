<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use Backendbase\Infrastructure\Configuration\Aws\SnsSettings;
use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Override;
use UnexpectedValueException;

use function is_string;

final readonly class SnsNotifier implements NotificationProvider
{
    public const string TYPE = 'sms';

    public function __construct(private SnsClient $client, private SnsSettings $settings)
    {
    }

    #[Override]
    public function type(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function notify(Notification $params): NotificationResult
    {
        if (! $params instanceof SmsNotification) {
            throw new UnexpectedValueException('SNS requires an SMS notification.');
        }

        $client = $this->client;
        try {
            $result = $client->publish([
                'PhoneNumber' => $params->phoneNumber(),
                'Message' => $params->message(),
                'MessageAttributes' => $this->messageAttributes(),
            ]);
        } catch (AwsException $exception) {
            throw new NotificationProviderFailed(self::TYPE, $exception);
        }

        $messageId = $result['MessageId'] ?? null;
        if (! is_string($messageId) || $messageId === '') {
            throw new UnexpectedValueException('SNS returned no message identifier.');
        }

        return NotificationResult::delivered(
            $params->type(),
            $messageId,
        );
    }

    /** @return array<string, array{DataType: string, StringValue: string}> */
    private function messageAttributes(): array
    {
        $settings   = $this->settings;
        $smsType    = $settings->smsType();
        $attributes = [
            'AWS.SNS.SMS.SMSType' => [
                'DataType' => 'String',
                'StringValue' => $smsType,
            ],
        ];
        $senderId   = $settings->senderId();
        if ($senderId === null || $senderId === '') {
            return $attributes;
        }

        $attributes['AWS.SNS.SMS.SenderID'] = [
            'DataType' => 'String',
            'StringValue' => $senderId,
        ];

        return $attributes;
    }
}
