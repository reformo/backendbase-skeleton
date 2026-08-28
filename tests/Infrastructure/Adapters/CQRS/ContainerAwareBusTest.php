<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\CQRS;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
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
        $command = new AddNewExample(
            'example-id',
            ExampleType::SYSTEM,
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
        $query = new GetExampleGroupsByType(ExampleType::SYSTEM, null);

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
