<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Configuration\LoggingSettings;
use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => static function (ContainerInterface $container) {
            $settings = $container->get(LoggingSettings::class);
            $logger   = new Logger($settings->name(), timezone: new DateTimeZone('UTC'));
            $traceId  = $_SERVER['HTTP_X_REQUEST_ID'] ?? Uuid::uuid7()->toString();

            $logger->pushProcessor(static function (LogRecord $record) use ($traceId) {
                $record->extra['trace_id'] = $traceId;

                return $record;
            });

            $output    = "[%datetime%] [%extra.trace_id%] %channel%.%level_name%: %message% %context%\n";
            $handler   = new StreamHandler($settings->path(), $settings->level());
            $formatter = new LineFormatter($output);
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            return $logger;
        },
    ]);
};
