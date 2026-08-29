<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Aws;

use Aws\Credentials\Credentials;
use Backendbase\Infrastructure\Configuration\Aws\AwsClientSettings;

final class AwsClientConfigurationBuilder
{
    /** @return array<string, mixed> */
    public function build(AwsClientSettings $settings, float $timeoutSeconds): array
    {
        $configuration = [
            'region' => $settings->region(),
            'version' => 'latest',
            'http' => ['connect_timeout' => $timeoutSeconds, 'timeout' => $timeoutSeconds],
        ];
        $credentials   = $settings->credentials();

        if ($credentials->areConfigured()) {
            $accessKey                    = $credentials->accessKey();
            $secretKey                    = $credentials->secretKey();
            $configuration['credentials'] = new Credentials(
                $accessKey,
                $secretKey,
            );
        }

        $endpoint = $settings->endpoint();
        if ($endpoint !== '') {
            $configuration['endpoint'] = $endpoint;
        }

        return $configuration;
    }
}
