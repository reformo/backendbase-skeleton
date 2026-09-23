<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\Configuration\AwsSettings;
use Backendbase\Infrastructure\Configuration\DatabaseSettings;
use Backendbase\Infrastructure\Configuration\LoggingSettings;
use Backendbase\Infrastructure\Configuration\NotificationSettings;
use Backendbase\Infrastructure\Configuration\QueueSettings;
use Backendbase\Infrastructure\Configuration\RedisSettings;
use Backendbase\Shared\Configuration\ApiKeySettings;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Configuration\JwtSettings;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use DI\ContainerBuilder;

use function DI\autowire;
use function DI\create;

return static function (ContainerBuilder $containerBuilder, array $config): void {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => create(Settings::class)
            ->constructor($config),
        ApiKeySettings::class => autowire(ApiKeySettings::class),
        ApplicationRuntimeSettings::class => autowire(ApplicationRuntimeSettings::class),
        AwsSettings::class => autowire(AwsSettings::class),
        DatabaseSettings::class => autowire(DatabaseSettings::class),
        HttpHeaderSettings::class => autowire(HttpHeaderSettings::class),
        JwtSettings::class => autowire(JwtSettings::class),
        LoggingSettings::class => autowire(LoggingSettings::class),
        NotificationSettings::class => autowire(NotificationSettings::class),
        QueueSettings::class => autowire(QueueSettings::class),
        RedisSettings::class => autowire(RedisSettings::class),
    ]);
};
