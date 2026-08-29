<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Aws;

use UnexpectedValueException;

final readonly class AwsCredentials
{
    public function __construct(private string $accessKey, private string $secretKey)
    {
        if (($this->accessKey === '') === ($this->secretKey === '')) {
            return;
        }

        throw new UnexpectedValueException('The AWS access key and secret key must be configured together.');
    }

    public function areConfigured(): bool
    {
        return $this->accessKey !== '';
    }

    public function accessKey(): string
    {
        return $this->accessKey;
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }
}
