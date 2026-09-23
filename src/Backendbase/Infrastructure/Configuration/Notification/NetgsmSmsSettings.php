<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Notification;

use UnexpectedValueException;

use function in_array;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function mb_strlen;
use function trim;

final readonly class NetgsmSmsSettings
{
    /** @var array{username: string, password: string, sender: string, encoding: string, timeoutSeconds: float} */
    private array $values;

    public function __construct(mixed $settings)
    {
        if (! is_array($settings)) {
            throw new UnexpectedValueException('Netgsm SMS settings must be an array.');
        }

        $username = $settings['username'] ?? '';
        $password = $settings['password'] ?? '';
        $sender   = $settings['sender'] ?? '';
        $encoding = $settings['encoding'] ?? 'TR';
        $timeout  = $settings['timeoutSeconds'] ?? 5.0;
        if (! is_string($username) || trim($username) === '' || ! is_string($password) || trim($password) === '') {
            throw new UnexpectedValueException('Netgsm credentials are invalid.');
        }

        if (! is_string($sender) || mb_strlen($sender) < 3 || mb_strlen($sender) > 11) {
            throw new UnexpectedValueException('Netgsm sender must have 3 to 11 characters.');
        }

        if (! in_array($encoding, ['UTF-8', 'TR', 'UNICODE'], true)) {
            throw new UnexpectedValueException('Netgsm encoding is invalid.');
        }

        if ((! is_float($timeout) && ! is_int($timeout)) || ! is_finite((float) $timeout) || $timeout <= 0) {
            throw new UnexpectedValueException('Netgsm timeout must be positive.');
        }

        $this->values = [
            'username' => $username,
            'password' => $password,
            'sender' => $sender,
            'encoding' => $encoding,
            'timeoutSeconds' => (float) $timeout,
        ];
    }

    public function username(): string
    {
        return $this->values['username'];
    }

    public function password(): string
    {
        return $this->values['password'];
    }

    public function sender(): string
    {
        return $this->values['sender'];
    }

    public function encoding(): string
    {
        return $this->values['encoding'];
    }

    public function timeoutSeconds(): float
    {
        return $this->values['timeoutSeconds'];
    }
}
