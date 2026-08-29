<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\Queue\QueueDriver;
use Backendbase\Infrastructure\Configuration\Queue\RabbitMQConnectionSettings;
use Backendbase\Infrastructure\Configuration\Queue\RabbitMQRuntimeSettings;
use Backendbase\Infrastructure\Configuration\Queue\RabbitMQTopologySettings;
use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use Backendbase\Shared\Configuration\ValidatedQueueSettings;
use Backendbase\Shared\Settings;

final readonly class QueueSettings
{
    /**
     * @var array{
     *     driver: QueueDriver,
     *     connection: RabbitMQConnectionSettings,
     *     runtimeConnection: RabbitMQRuntimeSettings,
     *     topology: RabbitMQTopologySettings,
     *     readinessTimeoutSeconds: float
     * }
     */
    private array $values;

    public function __construct(Settings $settings)
    {
        $rabbitMq         = $settings->get('rabbitmq');
        $connectionValues = ValidatedQueueSettings::rabbitMQConnection($rabbitMq);
        $connection       = new RabbitMQConnectionSettings($connectionValues);
        $runtimeValues    = ValidatedQueueSettings::rabbitMQRuntimeConnection($rabbitMq);
        $readiness        = ValidatedApplicationSettings::readiness($settings->get('readiness'));
        $queueValues      = ValidatedQueueSettings::queue($settings->get('queue'));
        $this->values     = [
            'driver' => QueueDriver::from($queueValues['driver']),
            'connection' => $connection,
            'runtimeConnection' => new RabbitMQRuntimeSettings($connection, $runtimeValues),
            'topology' => new RabbitMQTopologySettings(
                ValidatedQueueSettings::rabbitMQTopology($rabbitMq),
            ),
            'readinessTimeoutSeconds' => $readiness['timeoutSeconds'],
        ];
    }

    public function driver(): QueueDriver
    {
        return $this->values['driver'];
    }

    public function rabbitMqConnection(): RabbitMQConnectionSettings
    {
        return $this->values['connection'];
    }

    public function rabbitMqRuntimeConnection(): RabbitMQRuntimeSettings
    {
        return $this->values['runtimeConnection'];
    }

    public function rabbitMqTopology(): RabbitMQTopologySettings
    {
        return $this->values['topology'];
    }

    public function readinessTimeoutSeconds(): float
    {
        return $this->values['readinessTimeoutSeconds'];
    }
}
