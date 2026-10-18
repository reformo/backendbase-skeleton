<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\Aws\AwsClientSettings;
use Backendbase\Infrastructure\Configuration\Aws\AwsCredentials;
use Backendbase\Infrastructure\Configuration\Aws\AwsLocationSettings;
use Backendbase\Infrastructure\Configuration\Aws\ObjectStoreSettings;
use Backendbase\Infrastructure\Configuration\Aws\SnsSettings;
use Backendbase\Infrastructure\Configuration\Aws\SqsSettings;
use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use Backendbase\Shared\Configuration\ValidatedAwsSettings;
use Backendbase\Shared\Settings;

use function is_array;

final readonly class AwsSettings
{
    /**
     * @var array{
     *     client: AwsClientSettings,
     *     sqs: SqsSettings,
     *     sns: SnsSettings,
     *     'object-store': ObjectStoreSettings,
     *     readinessTimeoutSeconds: float
     * }
     */
    private array $values;

    public function __construct(Settings $settings)
    {
        $aws               = $settings->get('aws');
        $awsSections       = is_array($aws) ? $aws : [];
        $clientValues      = ValidatedAwsSettings::client($aws);
        $clientCredentials = new AwsCredentials(
            $clientValues['credentials']['key'],
            $clientValues['credentials']['secret'],
        );
        $objectStoreValues = ValidatedAwsSettings::objectStore($settings->get('object-store'));
        $objectCredentials = new AwsCredentials(
            $objectStoreValues['credentials']['key'],
            $objectStoreValues['credentials']['secret'],
        );
        $snsValues         = ValidatedAwsSettings::sns($awsSections['sns'] ?? null);
        $senderIdentifier  = $snsValues['sender-id'] ?? null;
        $readiness         = ValidatedApplicationSettings::readiness($settings->get('readiness'));
        $this->values      = [
            'client' => new AwsClientSettings(
                $clientCredentials,
                new AwsLocationSettings($clientValues['region'], $clientValues['endpoint']),
            ),
            'sqs' => new SqsSettings(ValidatedAwsSettings::sqs($awsSections['sqs'] ?? null)),
            'sns' => new SnsSettings(
                $snsValues['sms-type'] ?? 'Transactional',
                $senderIdentifier === '' ? null : $senderIdentifier,
            ),
            'object-store' => new ObjectStoreSettings($objectCredentials, $objectStoreValues),
            'readinessTimeoutSeconds' => $readiness['timeout-seconds'],
        ];
    }

    public function client(): AwsClientSettings
    {
        return $this->values['client'];
    }

    public function sqs(): SqsSettings
    {
        return $this->values['sqs'];
    }

    public function sns(): SnsSettings
    {
        return $this->values['sns'];
    }

    public function objectStore(): ObjectStoreSettings
    {
        return $this->values['object-store'];
    }

    public function readinessTimeoutSeconds(): float
    {
        return $this->values['readinessTimeoutSeconds'];
    }
}
