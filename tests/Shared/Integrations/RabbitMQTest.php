<?php

declare(strict_types=1);

namespace Tests\Shared\Integrations;

use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class RabbitMQTest extends TestCase
{
    #[Test]
    public function itPublishesAPersistentMessageToADurableDestination(): void
    {
        $channel    = $this->createMock(AMQPChannel::class);
        $connection = $this->connection($channel);

        $channel->expects($this->once())->method('confirm_select');
        $channel->expects($this->exactly(2))->method('queue_declare');
        $channel->expects($this->exactly(2))->method('exchange_declare');
        $channel->expects($this->exactly(2))->method('queue_bind');
        $channel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(static function (AMQPMessage $message): bool {
                    self::assertSame(
                        [
                            'messageBody' => 'Collection_Item_Added',
                            'eventVersion' => '1.0',
                            'data' => ['id' => 'item-1'],
                        ],
                        json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR),
                    );
                    self::assertSame('application/json', $message->get('content_type'));
                    self::assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $message->get('delivery_mode'));
                    self::assertSame('message-1', $message->get('message_id'));

                    return true;
                }),
                'backendbase',
                'collection.created',
                true,
            );
        $channel->expects($this->once())->method('wait_for_pending_acks_returns');

        $queue = new RabbitMQ($connection, $this->settings());
        $queue->publish([
            'queue' => 'events',
            'tag' => 'collection.created',
            'messageBody' => 'Collection_Item_Added',
            'messageId' => 'message-1',
            'eventVersion' => '1.0',
            'properties' => ['id' => 'item-1'],
        ]);
    }

    #[Test]
    public function itAcknowledgesAMessageAfterTheHandlerSucceeds(): void
    {
        $channel    = $this->createMock(AMQPChannel::class);
        $connection = $this->connection($channel);
        $message    = new AMQPMessage(
            json_encode(
                [
                    'messageBody' => 'Collection_Item_Added',
                    'eventVersion' => '1.0',
                    'data' => ['id' => 'item-1'],
                ],
                JSON_THROW_ON_ERROR,
            ),
            ['message_id' => 'message-1'],
        );
        $message->setChannel($channel)->setDeliveryInfo(1, false, 'backendbase', 'events');

        $channel->expects($this->once())->method('confirm_select');
        $channel->expects($this->exactly(2))->method('queue_declare');
        $channel->expects($this->exactly(2))->method('exchange_declare');
        $channel->expects($this->exactly(2))->method('queue_bind');
        $channel->expects($this->once())->method('basic_qos')->with(0, 1, false);
        $channel->expects($this->once())
            ->method('basic_consume')
            ->willReturnCallback(static function (
                string $queue,
                string $consumerTag,
                bool $noLocal,
                bool $noAck,
                bool $exclusive,
                bool $nowait,
                callable $callback,
            ) use ($message): string {
                self::assertSame('events', $queue);
                self::assertSame('backendbase-events', $consumerTag);
                self::assertFalse($noLocal);
                self::assertFalse($noAck);
                self::assertFalse($exclusive);
                self::assertFalse($nowait);
                $callback($message);

                return $consumerTag;
            });
        $channel->expects($this->once())->method('basic_ack')->with(1, false);
        $channel->expects($this->once())->method('is_consuming')->willReturn(false);

        $queue = new RabbitMQ($connection, $this->settings());
        $queue->consume(['queue' => 'events'], static function (array $payload): QueueMessageHandlingOutcome {
            self::assertSame('Collection_Item_Added', $payload['messageBody']);
            self::assertSame('1.0', $payload['eventVersion']);
            self::assertSame(['id' => 'item-1'], $payload['data']);
            self::assertSame('message-1', $payload['messageId']);
            self::assertSame('events', $payload['topic']);
            self::assertSame('events', $payload['tag']);

            return QueueMessageHandlingOutcome::ACKNOWLEDGE;
        });
    }

    #[Test]
    public function itRequeuesAMessageAfterTheHandlerFails(): void
    {
        $channel    = $this->createMock(AMQPChannel::class);
        $connection = $this->connection($channel);
        $message    = new AMQPMessage('{');
        $message->setChannel($channel)->setDeliveryInfo(1, false, '', 'events');

        $channel->expects($this->once())->method('confirm_select');
        $channel->expects($this->exactly(2))->method('queue_declare');
        $channel->expects($this->exactly(2))->method('exchange_declare');
        $channel->expects($this->exactly(2))->method('queue_bind');
        $channel->expects($this->once())->method('basic_qos');
        $channel->expects($this->once())
            ->method('basic_consume')
            ->willReturnCallback(static function (
                string $_queue,
                string $consumerTag,
                bool $_noLocal,
                bool $_noAck,
                bool $_exclusive,
                bool $_nowait,
                callable $callback,
            ) use ($message): string {
                $callback($message);

                return $consumerTag;
            });
        $channel->expects($this->once())->method('basic_nack')->with(1, false, true);
        $channel->expects($this->once())->method('is_consuming')->willReturn(false);

        $queue = new RabbitMQ($connection, $this->settings());
        $queue->consume(
            ['queue' => 'events'],
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::RETRY,
        );
    }

    #[Test]
    public function itRejectsADeadLetteredMessageWithoutRequeueing(): void
    {
        $channel    = $this->createMock(AMQPChannel::class);
        $connection = $this->connection($channel);
        $message    = new AMQPMessage('1');
        $message->setChannel($channel)->setDeliveryInfo(1, false, '', 'events');

        $channel->expects($this->once())->method('confirm_select');
        $channel->expects($this->exactly(2))->method('queue_declare');
        $channel->expects($this->exactly(2))->method('exchange_declare');
        $channel->expects($this->exactly(2))->method('queue_bind');
        $channel->expects($this->once())->method('basic_qos');
        $channel->expects($this->once())
            ->method('basic_consume')
            ->willReturnCallback(static function (
                string $_queue,
                string $consumerTag,
                bool $_noLocal,
                bool $_noAck,
                bool $_exclusive,
                bool $_nowait,
                callable $callback,
            ) use ($message): string {
                $callback($message);

                return $consumerTag;
            });
        $channel->expects($this->once())->method('basic_reject')->with(1, false);
        $channel->expects($this->once())->method('is_consuming')->willReturn(false);

        $queue = new RabbitMQ($connection, $this->settings());
        $queue->consume(
            ['queue' => 'events'],
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::REJECT,
        );
    }

    #[Test]
    public function itRequeuesAMessageWhenTheHandlerThrows(): void
    {
        $channel    = $this->createMock(AMQPChannel::class);
        $connection = $this->connection($channel);
        $message    = new AMQPMessage('{}');
        $message->setChannel($channel)->setDeliveryInfo(1, false, '', 'events');

        $channel->expects(self::once())->method('basic_consume')
            ->willReturnCallback(static function (
                string $_queue,
                string $consumerTag,
                bool $_noLocal,
                bool $_noAck,
                bool $_exclusive,
                bool $_nowait,
                callable $callback,
            ) use ($message): string {
                $callback($message);

                return $consumerTag;
            });
        $channel->expects(self::once())->method('basic_nack')->with(1, false, true);
        $channel->expects(self::once())->method('is_consuming')->willReturn(false);

        $queue = new RabbitMQ($connection, $this->settings());
        $queue->consume(['queue' => 'events'], static function (): never {
            throw new RuntimeException('Handler failed.');
        });
    }

    #[Test]
    public function itUsesTheDefaultExchangeWithoutBindingTheMainQueue(): void
    {
        $channel              = $this->createMock(AMQPChannel::class);
        $connection           = $this->connection($channel);
        $settings             = $this->settings();
        $settings['exchange'] = '';

        $channel->expects(self::once())->method('exchange_declare');
        $channel->expects(self::once())->method('queue_bind');
        $channel->expects(self::once())->method('basic_publish');

        new RabbitMQ($connection, $settings)->publish([
            'queue' => 'events',
            'messageBody' => 'Event',
        ]);
    }

    #[Test]
    public function itWaitsForTheConfiguredConsumerInterval(): void
    {
        $channel    = $this->createMock(AMQPChannel::class);
        $connection = $this->connection($channel);
        $channel->expects(self::once())->method('basic_consume');
        $channel->expects(self::exactly(2))->method('is_consuming')->willReturn(true, false);
        $channel->expects(self::once())->method('wait')->with(null, false, 1.0);

        new RabbitMQ($connection, $this->settings())->consume(
            ['queue' => 'events', 'waitTimeSeconds' => 1],
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::ACKNOWLEDGE,
        );
    }

    #[Test]
    public function itContinuesAfterAConsumerWaitTimeout(): void
    {
        $channel    = $this->createMock(AMQPChannel::class);
        $connection = $this->connection($channel);
        $channel->expects(self::once())->method('basic_consume');
        $channel->expects(self::exactly(2))->method('is_consuming')->willReturn(true, false);
        $channel->expects(self::once())
            ->method('wait')
            ->willThrowException(new AMQPTimeoutException());

        new RabbitMQ($connection, $this->settings())->consume(
            ['queue' => 'events'],
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::ACKNOWLEDGE,
        );
    }

    #[Test]
    public function itClosesAnOpenChannelAndConnection(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('is_open')->willReturn(true);
        $channel->expects(self::once())->method('close');
        $connection = $this->createMock(AbstractConnection::class);
        $connection->method('channel')->willReturn($channel);
        $connection->method('isConnected')->willReturn(true);
        $connection->expects(self::once())->method('close');
        $queue = new RabbitMQ($connection, $this->settings());
        $queue->publish(['queue' => 'events', 'messageBody' => 'Event']);

        unset($queue);
    }

    private function connection(AMQPChannel&MockObject $channel): AbstractConnection
    {
        $connection = $this->createStub(AbstractConnection::class);
        $connection->method('channel')->willReturn($channel);
        $connection->method('isConnected')->willReturn(false);
        $channel->method('is_open')->willReturn(false);

        return $connection;
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return [
            'queue' => 'backendbase-queue',
            'exchange' => 'backendbase',
            'exchangeType' => 'direct',
            'deadLetterExchange' => 'backendbase.dead-letter',
            'deadLetterQueueSuffix' => '.dead-letter',
            'messageRetentionMilliseconds' => 604800000,
            'prefetchCount' => 1,
        ];
    }
}
