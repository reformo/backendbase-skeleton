<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Health;

use Backendbase\Shared\Health\ReadinessCheck;
use Closure;
use Override;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use UnexpectedValueException;

final readonly class RabbitMQReadinessCheck implements ReadinessCheck
{
    /** @var Closure(): AbstractConnection */
    private Closure $connectionFactory;

    /**
     * @param array<string, mixed>                 $settings
     * @param (Closure(): AbstractConnection)|null $connectionFactory
     */
    public function __construct(array $settings, Closure|null $connectionFactory = null)
    {
        $configuration           = self::configuration($settings);
        $this->connectionFactory = $connectionFactory
            ?? static fn (): AbstractConnection => AMQPConnectionFactory::create($configuration);
    }

    /** @return non-empty-string */
    #[Override]
    public function name(): string
    {
        return 'queue';
    }

    #[Override]
    public function check(): void
    {
        $connection = ($this->connectionFactory)();
        $channel    = null;

        try {
            $channel = $connection->channel();
            if (! $channel->is_open()) {
                throw new UnexpectedValueException('The RabbitMQ readiness channel did not open.');
            }
        } finally {
            if ($channel !== null && $channel->is_open()) {
                $channel->close();
            }

            if ($connection->isConnected()) {
                $connection->close();
            }
        }
    }

    /** @param array<string, mixed> $settings */
    private static function configuration(array $settings): AMQPConnectionConfig
    {
        $timeout       = (float) $settings['readinessTimeoutSeconds'];
        $configuration = new AMQPConnectionConfig();
        $configuration->setHost($settings['host']);
        $configuration->setPort($settings['port']);
        $configuration->setUser($settings['user']);
        $configuration->setPassword($settings['password']);
        $configuration->setVhost($settings['vhost']);
        $configuration->setConnectionTimeout($timeout);
        $configuration->setReadTimeout($timeout);
        $configuration->setWriteTimeout($timeout);
        $configuration->setChannelRPCTimeout($timeout);
        $configuration->setKeepalive(false);
        $configuration->setHeartbeat(0);
        $configuration->setIsLazy(true);

        return $configuration;
    }
}
