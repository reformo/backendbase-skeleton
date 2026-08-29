<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Aws;

use Backendbase\Shared\Configuration\ValidatedAwsSettings;

/** @phpstan-import-type ObjectStoreSettings from ValidatedAwsSettings as ObjectStoreValues */
final readonly class ObjectStoreSettings
{
    /** @param ObjectStoreValues $values */
    public function __construct(private AwsCredentials $credentials, private array $values)
    {
    }

    public function credentials(): AwsCredentials
    {
        return $this->credentials;
    }

    public function region(): string
    {
        return $this->values['region'];
    }

    public function endpoint(): string
    {
        return $this->values['endpoint'];
    }

    public function bucket(): string
    {
        return $this->values['bucket'];
    }

    public function cdnBaseUrl(): string|null
    {
        return $this->values['cdnBaseUrl'];
    }
}
