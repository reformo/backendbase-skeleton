<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Aws\Sns\SnsClient;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Override;
use UnexpectedValueException;

use function in_array;
use function is_string;

final readonly class SnsNotifier implements Notify
{
    public const string TYPE = 'sms';

    /** @param array<string, mixed> $settings */
    public function __construct(private SnsClient $client, private array $settings)
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

        $result = $this->client->publish([
            'PhoneNumber' => $params->phoneNumber(),
            'Message' => $params->message(),
            'MessageAttributes' => $this->messageAttributes(),
        ]);

        $messageId = $result['MessageId'] ?? null;

        return NotificationResult::delivered(
            $params->type(),
            is_string($messageId) ? $messageId : null,
        );
    }

    /** @return array<string, array{DataType: string, StringValue: string}> */
    private function messageAttributes(): array
    {
        $smsType = $this->settings['smsType'] ?? 'Transactional';
        if (! is_string($smsType) || ! in_array($smsType, ['Promotional', 'Transactional'], true)) {
            throw new UnexpectedValueException('The SNS SMS type is invalid.');
        }

        $attributes = [
            'AWS.SNS.SMS.SMSType' => [
                'DataType' => 'String',
                'StringValue' => $smsType,
            ],
        ];
        $senderId   = $this->settings['senderId'] ?? null;
        if (! is_string($senderId) || $senderId === '') {
            return $attributes;
        }

        $attributes['AWS.SNS.SMS.SenderID'] = [
            'DataType' => 'String',
            'StringValue' => $senderId,
        ];

        return $attributes;
    }
}
