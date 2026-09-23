<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ReadRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as WriteRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1\NewExampleAddedCommand;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1\NewExampleAddedMessage;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\GreetingRequestedPayload;
use Backendbase\Domain\ExampleBoundedContext\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ServiceProviderTest extends TestCase
{
    #[Test]
    public function itProvidesRepositoriesAndIntegrationSubscribers(): void
    {
        self::assertSame([
            ReadRepositoryContract::class => ExampleReadRepository::class,
            WriteRepositoryContract::class => ExampleWriteRepository::class,
        ], ServiceProvider::getDefinitions());

        $subscribers = [];
        foreach (ServiceProvider::getIntegrationEventSubscribers() as $subscriber) {
            $subscribers[] = $subscriber;
        }

        self::assertCount(3, $subscribers);
        $externalSubscriber = $subscribers[1];
        if (! isset($externalSubscriber['messageFQCN'], $externalSubscriber['eventVersion'])) {
            self::fail('The external subscriber contract is incomplete.');
        }

        self::assertSame(NewExampleAddedMessage::class, $externalSubscriber['messageFQCN']);
        self::assertSame('1.0', $externalSubscriber['eventVersion']);
        $greetingSubscriber = $subscribers[2];
        if (! isset($greetingSubscriber['messageFQCN'], $greetingSubscriber['eventVersion'])) {
            self::fail('The greeting subscriber contract is incomplete.');
        }

        self::assertSame(GreetingRequestedPayload::class, $greetingSubscriber['messageFQCN']);
        self::assertSame('1.0', $greetingSubscriber['eventVersion']);
    }

    #[Test]
    public function itExposesExternalMessageData(): void
    {
        $message = new NewExampleAddedMessage(
            'example-id',
            new NewExampleAddedCommand(
                'example-id',
                'system',
                42,
                'settings',
                true,
                'page-size',
                '25',
                ['unit' => 'items'],
            ),
        );

        self::assertSame('example-id', $message->exampleId());
        self::assertSame('system', $message->type());
        self::assertSame(42, $message->typeTargetId());
        self::assertSame('settings', $message->group());
        self::assertTrue($message->isActive());
        self::assertSame('page-size', $message->key());
        self::assertSame('25', $message->value());
        self::assertSame(['unit' => 'items'], $message->details());
        self::assertSame($message->toArray(), $message->jsonSerialize());
    }

    #[Test]
    public function itRejectsMismatchedExternalMessageIdentifiers(): void
    {
        $this->expectException(UnexpectedValueException::class);

        new NewExampleAddedMessage(
            'message-example-id',
            new NewExampleAddedCommand(
                'command-example-id',
                'system',
                null,
                'settings',
                true,
                'page-size',
                '25',
                [],
            ),
        );
    }
}
