<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\CQRS;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Infrastructure\Adapters\CQRS\AttributeHandlerResolver;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareCommandBus;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareQueryBus;
use Backendbase\Infrastructure\Adapters\CQRS\RegistryHandlerResolver;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\CQRS\QueryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use Tests\Infrastructure\Adapters\CQRS\Fixtures\AttributedCommand;
use Tests\Infrastructure\Adapters\CQRS\Fixtures\RegistryCommand;
use Tests\Infrastructure\Adapters\CQRS\Fixtures\RegistryQuery;
use UnexpectedValueException;

final class ContainerAwareBusTest extends TestCase
{
    #[Test]
    public function itExposesTheConfiguredHandlerName(): void
    {
        self::assertSame('handler', new CQRSHandler('handler')->handlerName);
    }

    #[Test]
    public function itRejectsACommandContainerEntryWithTheWrongType(): void
    {
        $resolver = new RegistryHandlerResolver([AddEntry::class => CommandHandler::class]);
        $bus      = new ContainerAwareCommandBus($this->invalidContainer(), $resolver);
        $command  = new AddEntry(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'key',
            'value',
            new Acl(['full-privileges']),
        );

        $this->expectException(UnexpectedValueException::class);

        $bus->handle($command);
    }

    #[Test]
    public function itRejectsAQueryContainerEntryWithTheWrongType(): void
    {
        $resolver = new RegistryHandlerResolver([GetEntryGroupsByType::class => QueryHandler::class]);
        $bus      = new ContainerAwareQueryBus($this->invalidContainer(), $resolver);
        $query    = new GetEntryGroupsByType(EntryType::SYSTEM, null);

        $this->expectException(UnexpectedValueException::class);

        $bus->handle($query);
    }

    #[Test]
    public function itDispatchesAnUnattributedCommandThroughTheRegistry(): void
    {
        $command = new RegistryCommand();
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle')->with($command);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(CommandHandler::class)->willReturn($handler);
        $resolver = new RegistryHandlerResolver([RegistryCommand::class => CommandHandler::class]);

        new ContainerAwareCommandBus($container, $resolver)->handle($command);
    }

    #[Test]
    public function itDispatchesAnUnattributedQueryThroughTheRegistry(): void
    {
        $query   = new RegistryQuery();
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle')->with($query)->willReturn('found');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(QueryHandler::class)->willReturn($handler);
        $resolver = new RegistryHandlerResolver([RegistryQuery::class => QueryHandler::class]);

        self::assertSame('found', new ContainerAwareQueryBus($container, $resolver)->handle($query));
    }

    #[Test]
    public function itRejectsAnUnregisteredMessage(): void
    {
        $resolver = new RegistryHandlerResolver([]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(RegistryCommand::class);

        $resolver->handlerFor(new RegistryCommand());
    }

    #[Test]
    public function attributeModeRequiresAHandlerAttribute(): void
    {
        $this->expectException(UnexpectedValueException::class);

        new AttributeHandlerResolver()->handlerFor(new RegistryCommand());
    }

    #[Test]
    public function attributeModeResolvesASeparatelyAttributedCommand(): void
    {
        self::assertSame(CommandHandler::class, new AttributeHandlerResolver()->handlerFor(new AttributedCommand()));
    }

    private function invalidContainer(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new stdClass());

        return $container;
    }
}
