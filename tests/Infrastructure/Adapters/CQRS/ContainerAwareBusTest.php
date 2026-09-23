<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\CQRS;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareCommandBus;
use Backendbase\Infrastructure\Adapters\CQRS\ContainerAwareQueryBus;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
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
        $bus     = new ContainerAwareCommandBus($this->invalidContainer());
        $command = new AddEntry(
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
        $bus   = new ContainerAwareQueryBus($this->invalidContainer());
        $query = new GetEntryGroupsByType(EntryType::SYSTEM, null);

        $this->expectException(UnexpectedValueException::class);

        $bus->handle($query);
    }

    private function invalidContainer(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new stdClass());

        return $container;
    }
}
