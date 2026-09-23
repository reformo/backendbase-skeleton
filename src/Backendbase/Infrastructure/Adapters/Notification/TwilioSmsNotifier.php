<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Configuration\Notification\TwilioSmsSettings;
use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationProviderFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Override;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

use function is_object;
use function is_string;
use function json_decode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

final readonly class TwilioSmsNotifier implements NotificationProvider
{
    public const string TYPE = 'sms';

    public function __construct(private ClientInterface $client, private TwilioSmsSettings $settings)
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
        if (! $notification instanceof SmsNotification) {
            throw new UnexpectedValueException('Twilio requires an SMS notification.');
        }

        $settings   = $this->settings;
        $accountSid = $settings->accountSid();
        $authToken  = $settings->authToken();
        $from       = $settings->from();
        $timeout    = $settings->timeoutSeconds();
        $client     = $this->client;
        try {
            $response = $client->request('POST', 'https://api.twilio.com/2010-04-01/Accounts/' . $accountSid . '/Messages.json', [
                'auth' => [$accountSid, $authToken],
                'form_params' => [
                    'To' => $notification->phoneNumber(),
                    'From' => $from,
                    'Body' => $notification->message(),
                ],
                'connect_timeout' => $timeout,
                'timeout' => $timeout,
            ]);
        } catch (GuzzleException $exception) {
            throw new NotificationProviderFailed(self::TYPE, $exception);
        }

        $httpStatus = $response->getStatusCode();
        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new NotificationProviderFailed(self::TYPE, new UnexpectedValueException('Twilio returned an unsuccessful HTTP status.'));
        }

        $result = self::result($response);
        $status = $result->status ?? null;
        if ($status === 'failed' || $status === 'undelivered' || $status === 'canceled') {
            throw new NotificationProviderFailed(self::TYPE, new UnexpectedValueException('Twilio rejected the SMS.'));
        }

        $messageId = $result->sid ?? null;
        if (! is_string($messageId) || preg_match('/^(SM|MM)[0-9a-fA-F]{32}$/', $messageId) !== 1) {
            throw new UnexpectedValueException('Twilio returned no valid message identifier.');
        }

        return NotificationResult::delivered(self::TYPE, $messageId);
    }

    private static function result(ResponseInterface $response): object
    {
        try {
            $result = json_decode((string) $response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Twilio returned invalid JSON.', 0, $exception);
        }

        if (! is_object($result)) {
            throw new UnexpectedValueException('Twilio returned an invalid response.');
        }

        return $result;
    }
}
