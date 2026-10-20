<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Aws\S3\S3ClientInterface;
use Aws\SesV2\SesV2Client;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Adapters\Mapping\ObjectMapper;
use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Services\Translator;
use CuyZ\Valinor\Cache\FileSystemCache;
use CuyZ\Valinor\Cache\FileWatchingCache;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Tests\Architecture\Support\AdapterDependencies;
use Tests\Architecture\Support\ArchitectureDependencies;

final class RuntimeCompositionTest extends TestCase
{
    #[DataProvider('languages')]
    public function testTranslatorFactorySelectsTheSupportedRequestLanguage(string|null $header, string $locale): void
    {
        $previous = $_SERVER;
        try {
            unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            if ($header !== null) {
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header;
            }

            $container  = ProductionContainerFixture::configured([]);
            $translator = $container->get(Translator::class);
            self::assertSame($locale . '.composition-missing-key', $translator->translate('composition-missing-key'));
        } finally {
            $_SERVER = $previous;
        }
    }

    /** @return iterable<string, array{string|null, string}> */
    public static function languages(): iterable
    {
        yield 'absent' => [null, 'tr-TR'];
        yield 'empty' => ['', 'tr-TR'];
        yield 'unsupported' => ['xx-XX', 'tr-TR'];
        yield 'weighted English' => ['en-US;q=0.9,tr-TR;q=0.8', 'en-US'];
    }

    #[DataProvider('environments')]
    public function testEnvironmentSelectsTheProductionMappingAndDatabaseFactories(string $environment): void
    {
        $container     = ProductionContainerFixture::configured(['env' => $environment, 'doctrine' => ['connect' => 'pdo-sqlite:///:memory:']]);
        $configuration = $container->get(Configuration::class);
        $cache         = $configuration->getMetadataCache();
        self::assertInstanceOf($environment === 'dev' ? ArrayAdapter::class : PhpFilesAdapter::class, $cache);
        $mapper   = $container->get(ObjectMapper::class);
        $property = new ReflectionProperty(ObjectMapper::class, 'cache');
        self::assertInstanceOf($environment === 'dev' ? FileWatchingCache::class : FileSystemCache::class, $property->getValue($mapper));
        $manager = $container->get(EntityManagerInterface::class);
        self::assertSame($configuration, $manager->getConfiguration());
        $connection = $container->get(Connection::class);
        self::assertSame($connection, $manager->getConnection());
        $connection->close();
    }

    #[DataProvider('queues')]
    public function testBothQueuePortsResolveTheSelectedOwnedAdapter(string $driver, string $implementation): void
    {
        $container = ProductionContainerFixture::configured(['queue' => ['driver' => $driver]], [LoggerInterface::class => new NullLogger()]);
        $publisher = $container->get(MessagePublisher::class);
        self::assertSame($implementation, $publisher::class);
        self::assertSame($publisher, $container->get(MessageConsumer::class));
        $outbound = AdapterDependencies::outbound(ArchitectureDependencies::source());
        $classes  = AdapterDependencies::classSet($outbound);
        self::assertArrayHasKey($publisher::class, $classes);
    }

    #[DataProvider('endpoints')]
    public function testAwsFactoriesPreserveEndpointAndTimeoutPolicy(string $endpoint): void
    {
        $container = ProductionContainerFixture::configured([
            'aws' => ['endpoint' => $endpoint],
            'object-store' => ['endpoint' => $endpoint],
            'readiness' => ['timeout-seconds' => 1.5],
        ]);
        foreach ([S3ClientInterface::class, SqsClient::class, SnsClient::class, SesV2Client::class] as $class) {
            $client = $container->get($class);
            $uri    = $client->getEndpoint();
            self::assertSame($endpoint === '' ? 'https' : 'http', $uri->getScheme());
            if ($endpoint !== '') {
                self::assertSame($endpoint, (string) $uri);
            }

            $operation = match ($class) {
                S3ClientInterface::class => 'ListBuckets',
                SqsClient::class => 'ListQueues',
                SnsClient::class => 'ListTopics',
                default => 'ListEmailIdentities',
            };
            $command = $client->getCommand($operation);
            $http    = $command['@http'];
            self::assertSame(1.5, $http['timeout']);
            self::assertSame(1.5, $http['connect_timeout']);
        }

        $s3 = $container->get(S3ClientInterface::class);
        self::assertSame($endpoint !== '', $s3->getConfig('use_path_style_endpoint'));
    }

    /** @return iterable<string, array{string}> */
    public static function environments(): iterable
    {
        yield 'development' => ['dev'];
        yield 'test' => ['test'];
        yield 'CI' => ['ci'];
        yield 'stage' => ['stage'];
        yield 'production' => ['production'];
    }

    /** @return iterable<string, array{string, class-string}> */
    public static function queues(): iterable
    {
        yield 'RabbitMQ' => ['rabbitmq', RabbitMQ::class];
        yield 'SQS' => ['sqs', SqsQueue::class];
    }

    /** @return iterable<string, array{string}> */
    public static function endpoints(): iterable
    {
        yield 'service endpoint' => [''];
        yield 'local endpoint' => ['http://127.0.0.1:4566'];
    }
}
