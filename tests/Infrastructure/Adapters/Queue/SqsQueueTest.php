<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class SqsQueueTest extends TestCase
{
    private const string QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/123456789012/events';

    #[Test]
    public function itPublishesTheBackendbaseMessageEnvelope(): void
    {
        $handler = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame('SendMessage', $command->getName());
                self::assertSame(self::QUEUE_URL, $command['QueueUrl']);
                self::assertSame(
                    [
                        'messageBody' => 'Collection_Item_Added',
                        'messageId' => 'message-1',
                        'eventVersion' => '1.0',
                        'data' => ['id' => 'item-1'],
                        'tag' => 'collection.created',
                    ],
                    json_decode($command['MessageBody'], true, 512, JSON_THROW_ON_ERROR),
                );

                return new Result(['MessageId' => 'sqs-message-1']);
            },
        ]);
        $queue   = new SqsQueue(self::sqsClient($handler), self::settings());

        $result = $queue->publish(new Message(
            'Collection_Item_Added',
            ['id' => 'item-1'],
            'message-1',
            '1.0',
            'events',
            'collection.created',
        ));

        self::assertSame('sqs-message-1', $result->messageId());
    }

    #[Test]
    public function itDeletesAnAcknowledgedMessage(): void
    {
        $handler = new MockHandler([
            new Result([
                'Messages' => [
                    [
                        'Body' => '{"messageBody":"Collection_Item_Added","messageId":"message-1","eventVersion":"1.0","data":{"id":"item-1"},"tag":"collection.created"}',
                        'MessageId' => 'sqs-message-1',
                        'ReceiptHandle' => 'receipt-1',
                    ],
                ],
            ]),
            static function (CommandInterface $command): Result {
                self::assertSame('DeleteMessage', $command->getName());
                self::assertSame(self::QUEUE_URL, $command['QueueUrl']);
                self::assertSame('receipt-1', $command['ReceiptHandle']);

                return new Result();
            },
        ]);
        $queue   = new SqsQueue(self::sqsClient($handler), self::settings());

        $queue->consume(new MessageSubscription('events'), static function (Message $message): QueueMessageHandlingOutcome {
            self::assertSame('Collection_Item_Added', $message->body());
            self::assertSame('message-1', $message->id());
            self::assertSame('1.0', $message->eventVersion());
            self::assertSame(['id' => 'item-1'], $message->data());
            self::assertSame('events', $message->destination());
            self::assertSame('collection.created', $message->routingKey());

            return QueueMessageHandlingOutcome::ACKNOWLEDGE;
        });

        self::assertCount(0, $handler);
    }

    #[Test]
    public function itIgnoresMalformedReceiveMessageCollections(): void
    {
        $invalidCollection = new SqsQueue(
            new MalformedSqsClient(new Result(['Messages' => 'invalid'])),
            self::settings(),
        );
        $invalidCollection->consume(new MessageSubscription('events'), static function (): never {
            self::fail('A malformed collection must not reach the handler.');
        });

        $invalidMessage = new SqsQueue(
            new MalformedSqsClient(new Result(['Messages' => ['invalid']])),
            self::settings(),
        );
        $invalidMessage->consume(new MessageSubscription('events'), static function (): never {
            self::fail('A malformed message must not reach the handler.');
        });

        self::addToAssertionCount(2);
    }

    #[Test]
    public function itLeavesFailedAndRetryableMessagesOnTheQueue(): void
    {
        $handler = new MockHandler([
            new Result([
                'Messages' => [
                    ['Body' => '{', 'MessageId' => 'first'],
                    ['Body' => '1', 'MessageId' => 'second'],
                ],
            ]),
        ]);
        $calls   = 0;
        $queue   = new SqsQueue(self::sqsClient($handler), self::settings());

        $queue->consume(new MessageSubscription('events'), static function (Message $message) use (&$calls): QueueMessageHandlingOutcome {
            ++$calls;
            if ($calls === 1) {
                self::assertSame('{', $message->body());

                throw new RuntimeException('Handler failed.');
            }

            self::assertSame('1', $message->body());

            return QueueMessageHandlingOutcome::RETRY;
        });

        self::assertSame(2, $calls);
        self::assertCount(0, $handler);
    }

    #[Test]
    public function itRequiresAReceiptHandleBeforeAcknowledgement(): void
    {
        $handler = new MockHandler([
            new Result(['Messages' => [['Body' => '{}', 'MessageId' => 'message-id']]]),
        ]);
        $queue   = new SqsQueue(self::sqsClient($handler), self::settings());

        $this->expectException(UnexpectedValueException::class);

        $queue->consume(
            new MessageSubscription('events'),
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::ACKNOWLEDGE,
        );
    }

    #[Test]
    public function itResolvesTheQueueUrlFromTheQueueName(): void
    {
        $handler  = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame('GetQueueUrl', $command->getName());
                self::assertSame('events', $command['QueueName']);

                return new Result(['QueueUrl' => self::QUEUE_URL]);
            },
            static function (CommandInterface $command): Result {
                self::assertSame('SendMessage', $command->getName());
                self::assertSame(self::QUEUE_URL, $command['QueueUrl']);

                return new Result(['MessageId' => 'message-id']);
            },
        ]);
        $settings = self::settings();
        unset($settings['queueUrl']);
        $queue = new SqsQueue(self::sqsClient($handler), $settings);

        self::assertSame(
            'message-id',
            $queue->publish(new Message('Event'))->messageId(),
        );
    }

    #[Test]
    public function itRejectsAnUnresolvedQueueUrl(): void
    {
        $settings = self::settings();
        unset($settings['queueUrl']);
        $queue = new SqsQueue(
            self::sqsClient(new MockHandler([new Result()])),
            $settings,
        );

        $this->expectException(UnexpectedValueException::class);

        $queue->publish(new Message('Event'));
    }

    #[Test]
    public function itRequiresAQueueNameWhenResolvingAUrl(): void
    {
        $settings = self::settings();
        unset($settings['queue'], $settings['queueUrl']);
        $queue = new SqsQueue(self::sqsClient(new MockHandler()), $settings);

        $this->expectException(UnexpectedValueException::class);

        $queue->publish(new Message('Event'));
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        return [
            'continuous' => false,
            'maxNumberOfMessages' => 10,
            'queue' => 'events',
            'queueUrl' => self::QUEUE_URL,
            'visibilityTimeout' => 30,
            'waitTimeSeconds' => 20,
        ];
    }

    private static function sqsClient(MockHandler $handler): SqsClient
    {
        return new SqsClient([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }
}
