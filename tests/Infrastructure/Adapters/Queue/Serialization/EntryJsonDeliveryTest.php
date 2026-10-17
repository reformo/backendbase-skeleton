<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue\Serialization;

use Backendbase\Domain\ExampleCatalog\Application\EntryWrites;
use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine\DoctrineEntryRepositoryTestCase;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationEventOutbox;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMessageStore;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventDispatcher;
use Backendbase\Infrastructure\Adapters\Queue\OutboxMessagePublisher;
use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ\RabbitMQMessageMapper;
use Backendbase\Infrastructure\Adapters\Queue\SqsMessageMapper;
use Backendbase\Shared\Integrations\ExternalIntegrationEventRegistry;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Operation\MessagePublicationResult;
use Backendbase\Shared\Services\EventManager\EventManager;
use DateTimeImmutable;
use DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\Support\Time\FrozenClock;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class EntryJsonDeliveryTest extends DoctrineEntryRepositoryTestCase
{
    private const string DETAILS = '{"emptyObject":{},"emptyList":[],"numberedObject":{"0":"first"},"nested":[{},[],{"0":null}],"text":"A & B <tag>","boolean":false,"null":null,"number":1.25}';

    #[Test]
    #[DataProvider('transports')]
    public function itPreservesJsonFromProducerThroughOutboxAndTransportToTheRegisteredConsumer(string $transport, string $detailsJson): void
    {
        $this->connection->executeStatement('CREATE TABLE integration_event_outbox (id TEXT PRIMARY KEY, event_name TEXT, event_version TEXT, destination TEXT, payload TEXT, occurred_at TEXT, created_at TEXT, available_at TEXT, published_at TEXT, claim_token TEXT, claim_expires_at TEXT, attempts INTEGER, last_failure_type TEXT)');
        $now     = new DateTimeImmutable('2026-10-16T10:00:00Z');
        $clock   = new FrozenClock($now);
        $writes  = new EntryWrites($this->writeRepository(), $clock);
        $outbox  = new DoctrineIntegrationEventOutbox($this->connection, $clock, 'events');
        $details = (array) json_decode($detailsJson, false, 512, JSON_THROW_ON_ERROR);
        $command = new AddEntry('018f0000-0000-7000-8000-000000000001', EntryType::SYSTEM, null, 'settings', true, 'key', 'value', $details);
        $event   = $this->connection->transactional(static function () use ($writes, $outbox, $command): EntryAdded {
            $event = $writes->add($command);
            $outbox->append($event);

            return $event;
        });
        $store   = new DoctrineOutboxMessageStore($this->connection);
        $claimed = $store->claimNext($now, $now->modify('+60 seconds'));
        self::assertNotNull($claimed);
        self::assertSame(json_encode($event->getEventArguments(), JSON_THROW_ON_ERROR), $claimed->payload());

        $outgoing  = null;
        $publisher = $this->createMock(MessagePublisher::class);
        $publisher->expects(self::once())->method('publish')->willReturnCallback(static function (Message $message) use (&$outgoing): MessagePublicationResult {
            $outgoing = $message;

            return new MessagePublicationResult('transport-id');
        });
        $relay  = new OutboxMessagePublisher($publisher, new NullLogger());
        $result = $relay->publish($claimed);
        self::assertTrue($result->isSuccessful());
        self::assertInstanceOf(Message::class, $outgoing);
        $received = $this->throughTransport($outgoing, $transport, $detailsJson);
        self::assertSame($claimed->id(), $received->id());
        self::assertSame('Example_NewExampleAdded', $received->body());
        self::assertSame('1.0', $received->eventVersion());
        self::assertSame($claimed->occurredAt(), $received->occurredAt());
        self::assertSame('events', $received->destination());
        self::assertSame(json_encode($event->getEventArguments(), JSON_THROW_ON_ERROR), json_encode($received->data(), JSON_THROW_ON_ERROR));
        $dispatcher = $this->dispatcher($event, $detailsJson);
        $dispatcher->dispatch('Example_NewExampleAdded_Event', '1.0', $received->data());
    }

    /** @return iterable<string, array{string, string}> */
    public static function transports(): iterable
    {
        yield 'SQS' => ['sqs', self::DETAILS];
        yield 'RabbitMQ' => ['rabbitmq', self::DETAILS];

        $nested = ['leaf' => 'value'];
        for ($level = 1; $level < 62; $level++) {
            $nested = ['child' => $nested];
        }

        $deepDetails = json_encode(['nested' => $nested], JSON_THROW_ON_ERROR);

        yield 'SQS depth boundary' => ['sqs', $deepDetails];
        yield 'RabbitMQ depth boundary' => ['rabbitmq', $deepDetails];
    }

    private function throughTransport(Message $message, string $transport, string $detailsJson): Message
    {
        if ($transport === 'sqs') {
            $body = SqsMessageMapper::outgoing($message);
            $this->assertWireDetails($body, $detailsJson);

            return SqsMessageMapper::incoming(['Body' => $body], 'events');
        }

        $outbound = RabbitMQMessageMapper::outboundMessage($message);
        $this->assertWireDetails($outbound->getBody(), $detailsJson);

        return RabbitMQMessageMapper::inboundMessage($outbound, 'events');
    }

    private function assertWireDetails(string $body, string $detailsJson): void
    {
        $wire    = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        $data    = $wire->data;
        $command = $data->command;
        self::assertSame($detailsJson, json_encode($command->details, JSON_THROW_ON_ERROR));
    }

    private function dispatcher(EntryAdded $event, string $detailsJson): ExternalIntegrationEventDispatcher
    {
        $manager = $this->createMock(EventManager::class);
        $manager->method('getSubscriber')->willReturn(['subscriber' => EntryAddedExternalSubscriber::class]);
        $manager->expects(self::once())->method('dispatchExternalEvent')->with(
            'Example_NewExampleAdded_Event',
            self::callback(static function (EntryAddedMessage $message) use ($event, $detailsJson): bool {
                self::assertSame(json_encode($event->getEventArguments(), JSON_THROW_ON_ERROR), json_encode($message->toArray(), JSON_THROW_ON_ERROR));
                self::assertSame($detailsJson, json_encode($message->details(), JSON_THROW_ON_ERROR));

                return true;
            }),
        );
        $builder = new ContainerBuilder();
        $modules = require 'config/dependencies/modules.php';
        $modules($builder);
        $container = $builder->build();
        $registry  = $container->get(ExternalIntegrationEventRegistry::class);

        return new ExternalIntegrationEventDispatcher($manager, $registry);
    }
}
