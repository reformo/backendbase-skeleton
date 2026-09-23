<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Aws\MockHandler;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\QueueGreetingHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\QueueGreeting;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\IdentityAndAccess\Application\QueryHandlers\ListAccountsHandler;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\ListAccounts;
use Backendbase\Infrastructure\Adapters\CQRS\AttributeHandlerResolver;
use Backendbase\Infrastructure\Adapters\CQRS\RegistryHandlerResolver;
use Backendbase\Infrastructure\Health\RabbitMQConnectionFactory;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\HandlerResolver;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Health\ReadinessChecks;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function array_keys;

final class ProductionContainerTest extends TestCase
{
    #[Test]
    public function itResolvesImportantPortsAndReadinessChecksFromProductionDefinitions(): void
    {
        $container = ProductionContainerFixture::build([
            AbstractConnection::class => $this->createStub(AbstractConnection::class),
            Connection::class => $this->createStub(Connection::class),
            EntityManagerInterface::class => $this->createStub(EntityManagerInterface::class),
            LoggerInterface::class => new NullLogger(),
            RedisJsonInterface::class => $this->createStub(RedisJsonInterface::class),
            S3ClientInterface::class => $this->createStub(S3ClientInterface::class),
            SnsClient::class => $this->createStub(SnsClient::class),
            SqsClient::class => $this->createStub(SqsClient::class),
        ]);

        foreach (ProductionPortBindings::all() as $port => $implementation) {
            self::assertInstanceOf($implementation, $container->get($port), $port);
        }

        self::assertInstanceOf(ReadinessChecks::class, $container->get(ReadinessChecks::class));
        self::assertInstanceOf(RegistryHandlerResolver::class, $container->get(HandlerResolver::class));
    }

    #[Test]
    public function itResolvesReadinessChecksForEveryQueueDriver(): void
    {
        foreach (['rabbitmq', 'sqs'] as $queueDriver) {
            $connectionFactory = $this->createStub(RabbitMQConnectionFactory::class);
            $connectionFactory->method('create')->willReturn($this->createStub(AbstractConnection::class));
            $container = ProductionContainerFixture::build([
                Connection::class => $this->createStub(Connection::class),
                RabbitMQConnectionFactory::class => $connectionFactory,
                RedisJsonInterface::class => $this->createStub(RedisJsonInterface::class),
                S3ClientInterface::class => self::s3Client(),
                SqsClient::class => self::sqsClient(),
            ], $queueDriver);

            $report = $container->get(ReadinessChecks::class)->run()->jsonSerialize();

            self::assertSame(['mysql', 'redis', 'queue', 'objectStore'], array_keys($report['checks']));
        }
    }

    #[Test]
    public function itLoadsHandlerMappingsFromBothContextsInRegistryMode(): void
    {
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())->method('execute');
        $page       = new EntryGroupPage(['settings'], 1);
        $repository = $this->createMock(EntryReadRepository::class);
        $repository->expects(self::once())->method('getEntryGroupsByType')->willReturn($page);
        $container = ProductionContainerFixture::build([
            IntegrationEventTransaction::class => $transaction,
            EntryReadRepository::class => $repository,
        ]);
        $resolver  = $container->get(HandlerResolver::class);
        self::assertInstanceOf(RegistryHandlerResolver::class, $resolver);

        self::assertSame(
            QueueGreetingHandler::class,
            $resolver->handlerFor(new QueueGreeting('Ada', new Acl(['full-privileges']))),
        );
        self::assertSame(
            ListAccountsHandler::class,
            $resolver->handlerFor(new ListAccounts(new Acl(['full-privileges']))),
        );

        $container->get(CommandBus::class)->handle(new QueueGreeting('Ada', new Acl(['full-privileges'])));
        self::assertSame(
            $page,
            $container->get(QueryBus::class)->handle(new GetEntryGroupsByType(EntryType::SYSTEM, null)),
        );
    }

    #[Test]
    public function itCanSelectTheAttributeResolver(): void
    {
        $container = ProductionContainerFixture::build([], 'rabbitmq', AttributeHandlerResolver::class);

        self::assertInstanceOf(AttributeHandlerResolver::class, $container->get(HandlerResolver::class));
    }

    private static function s3Client(): S3Client
    {
        return new S3Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => new MockHandler(),
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }

    private static function sqsClient(): SqsClient
    {
        return new SqsClient([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => new MockHandler(),
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }
}
