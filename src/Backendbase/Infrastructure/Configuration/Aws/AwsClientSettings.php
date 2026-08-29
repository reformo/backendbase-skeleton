<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Aws;

final readonly class AwsClientSettings
{
    public function __construct(
        private AwsCredentials $credentials,
        private AwsLocationSettings $location,
    ) {
    }

    public function credentials(): AwsCredentials
    {
        return $this->credentials;
    }

    public function region(): string
    {
        return $this->location->region();
    }

    public function endpoint(): string
    {
        return $this->location->endpoint();
    }
}
