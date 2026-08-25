<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use InvalidArgumentException;
use Override;

use function preg_match;
use function trim;

final readonly class SmsNotification implements Notification
{
    private const string TYPE = 'sms';

    private string $phoneNumber;

    public function __construct(string $phoneNumber, private string $message)
    {
        $phoneNumber = trim($phoneNumber);
        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $phoneNumber) !== 1) {
            throw new InvalidArgumentException('The SMS phone number must use the E.164 format.');
        }

        if (trim($message) === '') {
            throw new InvalidArgumentException('The SMS message cannot be empty.');
        }

        $this->phoneNumber = $phoneNumber;
    }

    public function phoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    /** @return array{type: string, phoneNumber: string, message: string} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array{type: string, phoneNumber: string, message: string} */
    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'phoneNumber' => $this->phoneNumber,
            'message' => $this->message,
        ];
    }
}
