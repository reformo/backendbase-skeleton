<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Aws;

use UnexpectedValueException;

final readonly class AwsLocationSettings
{
    public function __construct(private string $region, private string $endpoint)
    {
        if ($this->region === '') {
            throw new UnexpectedValueException('The AWS region is missing.');
        }
    }

    public function region(): string
    {
        return $this->region;
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }
}
