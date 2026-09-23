<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Infrastructure\Configuration\Notification\NetgsmSmsSettings;
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
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;

final readonly class NetgsmSmsNotifier implements NotificationProvider
{
    public const string TYPE = 'sms';

    public function __construct(private ClientInterface $client, private NetgsmSmsSettings $settings)
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
            throw new UnexpectedValueException('Netgsm requires an SMS notification.');
        }

        $settings = $this->settings;
        $username = $settings->username();
        $password = $settings->password();
        $sender   = $settings->sender();
        $encoding = $settings->encoding();
        $timeout  = $settings->timeoutSeconds();
        $phone    = self::phoneNumber($notification->phoneNumber());
        $client   = $this->client;
        try {
            $response = $client->request('POST', 'https://api.netgsm.com.tr/sms/rest/v2/send', [
                'auth' => [$username, $password],
                'json' => [
                    'msgheader' => $sender,
                    'messages' => [['msg' => $notification->message(), 'no' => $phone]],
                    'encoding' => $encoding,
                ],
                'connect_timeout' => $timeout,
                'timeout' => $timeout,
            ]);
        } catch (GuzzleException $exception) {
            throw new NotificationProviderFailed(self::TYPE, $exception);
        }

        $httpStatus = $response->getStatusCode();
        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new NotificationProviderFailed(self::TYPE, new UnexpectedValueException('Netgsm returned an unsuccessful HTTP status.'));
        }

        $result = self::result($response);
        $code   = $result->code ?? null;
        if (! is_string($code)) {
            throw new UnexpectedValueException('Netgsm returned no result code.');
        }

        if ($code !== '00') {
            throw new NotificationProviderFailed(self::TYPE, new UnexpectedValueException('Netgsm rejected SMS with code ' . $code . '.'));
        }

        $jobId = $result->jobid ?? null;
        if (! is_string($jobId) || $jobId === '') {
            throw new UnexpectedValueException('Netgsm returned no job identifier.');
        }

        return NotificationResult::delivered(self::TYPE, $jobId);
    }

    private static function phoneNumber(string $phoneNumber): string
    {
        if (str_starts_with($phoneNumber, '+90')) {
            return substr($phoneNumber, 3);
        }

        return '00' . substr($phoneNumber, 1);
    }

    private static function result(ResponseInterface $response): object
    {
        try {
            $result = json_decode((string) $response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Netgsm returned invalid JSON.', 0, $exception);
        }

        if (! is_object($result)) {
            throw new UnexpectedValueException('Netgsm returned an invalid response.');
        }

        return $result;
    }
}
