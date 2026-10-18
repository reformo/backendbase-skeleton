<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Notification;

use UnexpectedValueException;

use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function trim;

final readonly class TwilioSmsSettings
{
    /** @var array{'account-sid': string, 'auth-token': string, from: string, 'timeout-seconds': float} */
    private array $values;

    public function __construct(mixed $settings)
    {
        if (! is_array($settings)) {
            throw new UnexpectedValueException('Twilio SMS settings must be an array.');
        }

        $accountSid = $settings['account-sid'] ?? '';
        $authToken  = $settings['auth-token'] ?? '';
        $from       = $settings['from'] ?? '';
        $timeout    = $settings['timeout-seconds'] ?? 5.0;
        if (! is_string($accountSid) || preg_match('/^AC[0-9a-fA-F]{32}$/', $accountSid) !== 1) {
            throw new UnexpectedValueException('Twilio account SID is invalid.');
        }

        if (! is_string($authToken) || trim($authToken) === '' || ! is_string($from) || trim($from) === '') {
            throw new UnexpectedValueException('Twilio token or sender is invalid.');
        }

        if ((! is_float($timeout) && ! is_int($timeout)) || ! is_finite((float) $timeout) || $timeout <= 0) {
            throw new UnexpectedValueException('Twilio timeout must be positive.');
        }

        $this->values = [
            'account-sid' => $accountSid,
            'auth-token' => $authToken,
            'from' => $from,
            'timeout-seconds' => (float) $timeout,
        ];
    }

    public function accountSid(): string
    {
        return $this->values['account-sid'];
    }

    public function authToken(): string
    {
        return $this->values['auth-token'];
    }

    public function from(): string
    {
        return $this->values['from'];
    }

    public function timeoutSeconds(): float
    {
        return $this->values['timeout-seconds'];
    }
}
