<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineExternalEffectInbox;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineInboxMessageTransaction;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationEventOutbox;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationEventTransaction;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationMessageLogCleaner;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMessageStore;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMonitor;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineQueueMessageFailureStore;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DQL\FirstFunction;
use Backendbase\Infrastructure\Adapters\Queue\OutboxMessagePublisher;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\Configuration\DatabaseSettings;
use Backendbase\Infrastructure\Messaging\OutboxRelayService;
use Backendbase\Infrastructure\Messaging\QueueMessageFailureService;
use Backendbase\Shared\Helpers\PathFinder;
use Backendbase\Shared\Integrations\IntegrationMessageLogCleaner;
use Backendbase\Shared\Integrations\OutboxMonitor;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Integrations\OutboxRelay;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Persistence\InboxMessageTransaction;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Persistence\Outbox\IntegrationEventOutbox;
use Backendbase\Shared\Persistence\OutboxMessageStore;
use Backendbase\Shared\Persistence\QueueMessageFailureStore;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use DoctrineExtensions\Query\Mysql\Cast;
use DoctrineExtensions\Query\Mysql\Now;
use DoctrineExtensions\Query\Mysql\UnixTimestamp;
use Psr\Container\ContainerInterface;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonArray;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonContains;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonExtract;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonSearch;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonUnquote;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        IntegrationEventTransaction::class => autowire(DoctrineIntegrationEventTransaction::class),
        IntegrationEventOutbox::class => autowire(DoctrineIntegrationEventOutbox::class),
        InboxMessageTransaction::class => autowire(DoctrineInboxMessageTransaction::class),
        ExternalEffectInbox::class => autowire(DoctrineExternalEffectInbox::class),
        OutboxRelay::class => autowire(OutboxRelayService::class),
        OutboxMessageStore::class => autowire(DoctrineOutboxMessageStore::class),
        OutboxPublisher::class => autowire(OutboxMessagePublisher::class),
        IntegrationMessageLogCleaner::class => autowire(DoctrineIntegrationMessageLogCleaner::class),
        OutboxMonitor::class => autowire(DoctrineOutboxMonitor::class),
        QueueMessageFailurePolicy::class => autowire(QueueMessageFailureService::class),
        QueueMessageFailureStore::class => autowire(DoctrineQueueMessageFailureStore::class),
        Configuration::class => static function (ContainerInterface $container) {
            $runtimeSettings = $container->get(ApplicationRuntimeSettings::class);
            $environment     = $runtimeSettings->environment();

            if ($environment !== Environment::DEV) {
                $cache = new PhpFilesAdapter('orm-cache', 0, 'var/cache/doctrine');
            } else {
                $cache = new ArrayAdapter();
            }

            $configuration = ORMSetup::createAttributeMetadataConfiguration(
                paths: PathFinder::doctrineEntityPaths(),
                isDevMode: $environment === Environment::DEV,
                cache: $cache,
            );
            $configuration->enableNativeLazyObjects(true);

            return $configuration;
        },
        Connection::class => static function (ContainerInterface $container) {
            $databaseSettings = $container->get(DatabaseSettings::class);
            $configuration    = $container->get(Configuration::class);
            assert($configuration instanceof Configuration);
            $configuration->addCustomStringFunction(FirstFunction::FUNCTION_NAME, FirstFunction::class);
            $configuration->addCustomStringFunction(JsonExtract::FUNCTION_NAME, JsonExtract::class);
            $configuration->addCustomStringFunction(JsonSearch::FUNCTION_NAME, JsonSearch::class);
            $configuration->addCustomStringFunction(JsonUnquote::FUNCTION_NAME, JsonUnquote::class);
            $configuration->addCustomStringFunction(JsonContains::FUNCTION_NAME, JsonContains::class);
            $configuration->addCustomStringFunction(JsonArray::FUNCTION_NAME, JsonArray::class);
            $configuration->addCustomStringFunction('UNIX_TIMESTAMP', UnixTimestamp::class);
            $configuration->addCustomStringFunction('NOW', Now::class);
            $configuration->addCustomStringFunction('CAST', Cast::class);

            $dsnParser                                              = new DsnParser(['mysql' => 'pdo_mysql']);
            $connectionSettings                                     = $dsnParser->parse($databaseSettings->dsn());
            $connectionSettings['driverOptions'][PDO::ATTR_TIMEOUT] = $databaseSettings->connectionTimeoutSeconds();

            return DriverManager::getConnection($connectionSettings, $configuration);
        },
        EntityManagerInterface::class => static function (ContainerInterface $container) {
            $connection = $container->get(Connection::class);
            assert($connection instanceof Connection);
            $configuration = $container->get(Configuration::class);
            assert($configuration instanceof Configuration);

            return new EntityManager($connection, $configuration);
        },
    ]);
};
