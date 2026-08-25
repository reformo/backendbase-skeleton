<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Aws;

use Aws\Credentials\Credentials;
use UnexpectedValueException;

use function is_array;
use function is_string;

final class AwsClientConfigurationBuilder
{
    /**
     * @param array<string, mixed> $awsSettings
     *
     * @return array<string, mixed>
     */
    public function build(array $awsSettings, float $timeoutSeconds): array
    {
        $region = $awsSettings['region'] ?? null;
        if (! is_string($region) || $region === '') {
            throw new UnexpectedValueException('The AWS region is missing.');
        }

        $configuration = [
            'region' => $region,
            'version' => 'latest',
            'http' => ['connect_timeout' => $timeoutSeconds, 'timeout' => $timeoutSeconds],
        ];
        $credentials   = $awsSettings['credentials'] ?? null;
        if (! is_array($credentials)) {
            throw new UnexpectedValueException('The AWS credentials configuration is invalid.');
        }

        $accessKey = $credentials['key'] ?? null;
        $secretKey = $credentials['secret'] ?? null;
        if (! is_string($accessKey) || ! is_string($secretKey)) {
            throw new UnexpectedValueException('The AWS credentials must be strings.');
        }

        if (($accessKey === '') !== ($secretKey === '')) {
            throw new UnexpectedValueException('The AWS access key and secret key must be configured together.');
        }

        if ($accessKey !== '') {
            $configuration['credentials'] = new Credentials($accessKey, $secretKey);
        }

        $endpoint = $awsSettings['endpoint'] ?? null;
        if (is_string($endpoint) && $endpoint !== '') {
            $configuration['endpoint'] = $endpoint;
        }

        return $configuration;
    }
}
