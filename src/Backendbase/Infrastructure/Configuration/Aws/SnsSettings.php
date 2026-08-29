<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Aws;

use UnexpectedValueException;

use function in_array;

final readonly class SnsSettings
{
    public function __construct(private string $smsType, private string|null $senderId)
    {
        if (! in_array($this->smsType, ['Promotional', 'Transactional'], true)) {
            throw new UnexpectedValueException('The SNS SMS type is invalid.');
        }
    }

    public function smsType(): string
    {
        return $this->smsType;
    }

    public function senderId(): string|null
    {
        return $this->senderId;
    }
}
