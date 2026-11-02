<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Infrastructure\Adapters\Queue\SqsTransport;
use Backendbase\Infrastructure\Configuration\Aws\SqsSettings;
use Backendbase\Infrastructure\Configuration\Queue\RabbitMQTopologySettings;
use Backendbase\Shared\Configuration\ValidatedAwsSettings;
use Backendbase\Shared\Configuration\ValidatedQueueSettings;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class QueueSettlementStateTest extends TestCase
{
    private const string QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/123456789012/events';

    #[DataProvider('outcomes')]
    public function testBothTransportsSettleTheSameApplicationOutcome(QueueMessageHandlingOutcome $outcome): void
    {
        $this->assertRabbitSettlement($outcome);
        $steps = [self::received('receipt-1')];
        if ($outcome === QueueMessageHandlingOutcome::ACKNOWLEDGE) {
            $steps[] = self::delete('receipt-1');
        }

        $handler = new MockHandler($steps);
        $queue   = self::sqs($handler);
        $queue->consume(new MessageSubscription('events'), static fn (): QueueMessageHandlingOutcome => $outcome);
        self::assertCount(0, $handler);
    }

    public function testSqsCanRedeliverAfterApplicationRejection(): void
    {
        $handler = new MockHandler([self::received('receipt-1'), self::received('receipt-2'), self::delete('receipt-2')]);
        $queue   = self::sqs($handler);
        $calls   = 0;
        $process = static function () use (&$calls): QueueMessageHandlingOutcome {
            $calls++;

            return $calls === 1 ? QueueMessageHandlingOutcome::REJECT : QueueMessageHandlingOutcome::ACKNOWLEDGE;
        };
        $queue->consume(new MessageSubscription('events'), $process);
        $queue->consume(new MessageSubscription('events'), $process);
        self::assertSame(2, $calls);
        self::assertCount(0, $handler);
    }

    /** @return iterable<string, array{QueueMessageHandlingOutcome}> */
    public static function outcomes(): iterable
    {
        yield 'acknowledge' => [QueueMessageHandlingOutcome::ACKNOWLEDGE];
        yield 'retry' => [QueueMessageHandlingOutcome::RETRY];
        yield 'reject' => [QueueMessageHandlingOutcome::REJECT];
    }

    private function assertRabbitSettlement(QueueMessageHandlingOutcome $outcome): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $open    = $channel->method('is_open');
        $open->willReturn(false);
        $consuming = $channel->method('is_consuming');
        $consuming->willReturn(false);
        $connection    = $this->createStub(AbstractConnection::class);
        $channelMethod = $connection->method('channel');
        $channelMethod->willReturn($channel);
        $connected = $connection->method('isConnected');
        $connected->willReturn(false);
        $message = new AMQPMessage('{}');
        $message->setChannel($channel);
        $message->setDeliveryInfo(1, false, '', 'events');
        $consumeExpectation = $channel->expects(self::once());
        $consume            = $consumeExpectation->method('basic_consume');
        $consume->willReturnCallback(static function (string $queue, string $tag, bool $local, bool $acknowledge, bool $exclusive, bool $wait, callable $callback) use ($message): string {
            $callback($message);

            return $tag;
        });
        $ackExpectation = $channel->expects(self::exactly($outcome === QueueMessageHandlingOutcome::ACKNOWLEDGE ? 1 : 0));
        $ack            = $ackExpectation->method('basic_ack');
        $ack->with(1, false);
        $retryExpectation = $channel->expects(self::exactly($outcome === QueueMessageHandlingOutcome::RETRY ? 1 : 0));
        $retry            = $retryExpectation->method('basic_nack');
        $retry->with(1, false, true);
        $rejectExpectation = $channel->expects(self::exactly($outcome === QueueMessageHandlingOutcome::REJECT ? 1 : 0));
        $reject            = $rejectExpectation->method('basic_reject');
        $reject->with(1, false);
        $settings = new RabbitMQTopologySettings(ValidatedQueueSettings::rabbitMQTopology(['queue' => 'events', 'exchange' => 'backendbase', 'exchange-type' => 'direct', 'dead-letter-exchange' => 'backendbase.dead-letter', 'dead-letter-queue-suffix' => '.dead-letter', 'message-retention-milliseconds' => 604800000, 'prefetch-count' => 1]));
        $rabbit   = new RabbitMQ($connection, $settings);
        $rabbit->consume(new MessageSubscription('events'), static fn (): QueueMessageHandlingOutcome => $outcome);
    }

    private static function sqs(MockHandler $handler): SqsQueue
    {
        $client   = new SqsClient(['credentials' => ['key' => 'test-key', 'secret' => 'test-secret'], 'handler' => $handler, 'region' => 'eu-central-1', 'version' => 'latest']);
        $settings = new SqsSettings(ValidatedAwsSettings::sqs(['continuous' => false, 'maximum-message-count' => 1, 'queue' => 'events', 'queue-url' => self::QUEUE_URL, 'visibility-timeout-seconds' => 30, 'wait-time-seconds' => 0]));

        return new SqsQueue(new SqsTransport($client, new NullLogger()), $settings);
    }

    /** @return Result<array{Messages: list<array{Body: string, MessageId: string, ReceiptHandle: string}>}> */
    private static function received(string $receipt): Result
    {
        return new Result(['Messages' => [['Body' => '{}', 'MessageId' => 'message-id', 'ReceiptHandle' => $receipt]]]);
    }

    /** @return callable(CommandInterface): Result<array{}> */
    private static function delete(string $receipt): callable
    {
        return static function (CommandInterface $command) use ($receipt): Result {
            self::assertSame('DeleteMessage', $command->getName());
            self::assertSame(self::QUEUE_URL, $command['QueueUrl']);
            self::assertSame($receipt, $command['ReceiptHandle']);

            return new Result();
        };
    }
}
