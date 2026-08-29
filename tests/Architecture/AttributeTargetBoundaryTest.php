<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;
use Backendbase\Shared\Domain\Attributes\DomainEventListener as DomainEventListenerAttribute;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventListener;
use PHPUnit\Framework\Attributes\Test;
use Tests\Architecture\Support\AttributeTargets;
use Tests\TestCase;

use function array_key_exists;
use function is_a;
use function preg_match;

final class AttributeTargetBoundaryTest extends TestCase
{
    #[Test]
    public function attributesDeclareOnePositionalClassTarget(): void
    {
        foreach ($this->targets() as $target) {
            self::assertCount(1, $target['arguments'], $target['source']);
            self::assertTrue(array_key_exists(0, $target['arguments']), $target['source']);
            self::assertNotNull($target['target'], $target['source']);
        }
    }

    #[Test]
    public function attributeTargetsStayInTheSameBoundedContext(): void
    {
        foreach ($this->targets() as $target) {
            self::assertSame(
                $this->context($target['source']),
                $this->context($target['target']),
                $target['source'],
            );
        }
    }

    #[Test]
    public function attributeTargetsImplementTheRequiredHandlerInterface(): void
    {
        foreach (AttributeTargets::forAttribute(CQRSHandler::class) as $target) {
            $isCommand = is_a($target['source'], Command::class, true);
            $isQuery   = is_a($target['source'], Query::class, true);
            self::assertTrue($isCommand || $isQuery, $target['source']);
            $expectedInterface = $isCommand
                ? CommandHandler::class
                : QueryHandler::class;
            self::assertNotNull($target['target']);
            self::assertTrue(is_a($target['target'], $expectedInterface, true), $target['source']);
        }

        foreach (AttributeTargets::forAttribute(DomainEventListenerAttribute::class) as $target) {
            self::assertTrue(is_a($target['source'], DomainEvent::class, true), $target['source']);
            self::assertNotNull($target['target']);
            self::assertTrue(is_a($target['target'], DomainEventListener::class, true), $target['source']);
        }
    }

    #[Test]
    public function theProductionContainerResolvesEveryAttributeTarget(): void
    {
        $container = $this->getAppInstance()->getContainer();
        self::assertNotNull($container);

        foreach ($this->targets() as $target) {
            self::assertNotNull($target['target']);
            self::assertTrue($container->has($target['target']), $target['source']);
            self::assertIsObject($container->get($target['target']), $target['source']);
        }
    }

    /**
     * @return list<array{
     *     source: class-string,
     *     arguments: array<array-key, mixed>,
     *     target: string|null
     * }>
     */
    private function targets(): array
    {
        return [
            ...AttributeTargets::forAttribute(CQRSHandler::class),
            ...AttributeTargets::forAttribute(DomainEventListenerAttribute::class),
        ];
    }

    private function context(string|null $class): string|null
    {
        if ($class === null) {
            return null;
        }

        return preg_match('#^Backendbase\\\\Domain\\\\([^\\\\]+)\\\\#', $class, $matches) === 1
            ? $matches[1]
            : null;
    }
}
