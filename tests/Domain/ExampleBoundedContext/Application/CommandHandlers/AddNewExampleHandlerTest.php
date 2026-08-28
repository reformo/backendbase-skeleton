<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Application\CommandHandlers;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\AddNewExampleHandler;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\NewExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventPublisher;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddNewExampleHandlerTest extends TestCase
{
    #[Test]
    public function itPublishesTheDomainEventInsideTheIntegrationTransaction(): void
    {
        $calls      = [];
        $command    = new AddNewExample(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
        );
        $repository = $this->createMock(ExampleWriteRepository::class);
        $repository->expects(self::once())
            ->method('add')
            ->with(self::callback(static fn (Example $example): bool => $example->id() === 'example-id'))
            ->willReturnCallback(static function (Example $_example) use (&$calls): void {
                $calls[] = 'repository';
            });
        $publisher = $this->createMock(DomainEventPublisher::class);
        $publisher->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent $_event) use (&$calls): void {
                $calls[] = 'domain-event';
            });
        $transaction = $this->createMock(IntegrationEventTransaction::class);
        $transaction->expects(self::once())
            ->method('execute')
            ->with(self::anything())
            ->willReturnCallback(static function (
                callable $transactionalWork,
            ) use (&$calls): void {
                $calls[] = 'transaction-start';
                $event   = $transactionalWork();
                self::assertInstanceOf(NewExampleAdded::class, $event);
                $calls[] = 'transaction-end';
            });

        $handler = new AddNewExampleHandler($repository, $transaction, $publisher);
        $handler->handle($command);

        self::assertSame(
            ['transaction-start', 'repository', 'domain-event', 'transaction-end'],
            $calls,
        );
    }
}
