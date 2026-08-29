<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Infrastructure\Adapters\Queue\SqsTransport;
use Backendbase\Infrastructure\Configuration\Aws\SqsSettings;
use Backendbase\Shared\Configuration\ValidatedAwsSettings;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        $queue   = self::queue($handler);

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
        $queue   = self::queue($handler);

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
        $invalidCollection = self::malformedQueue(new Result(['Messages' => 'invalid']));
        $invalidCollection->consume(new MessageSubscription('events'), static function (): never {
            self::fail('A malformed collection must not reach the handler.');
        });

        $invalidMessage = self::malformedQueue(new Result(['Messages' => ['invalid']]));
        $invalidMessage->consume(new MessageSubscription('events'), static function (): never {
            self::fail('A malformed message must not reach the handler.');
        });

        self::addToAssertionCount(2);
    }

    #[Test]
    public function itClampsReceiveOptionsToSqsLimits(): void
    {
        $minimumHandler = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame(1, $command['MaxNumberOfMessages']);
                self::assertSame(0, $command['VisibilityTimeout']);
                self::assertSame(0, $command['WaitTimeSeconds']);

                return new Result();
            },
        ]);
        self::queue($minimumHandler)->consume(
            new MessageSubscription('events', -1, 0, -1),
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::RETRY,
        );

        $maximumHandler = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame(10, $command['MaxNumberOfMessages']);
                self::assertSame(43200, $command['VisibilityTimeout']);
                self::assertSame(20, $command['WaitTimeSeconds']);

                return new Result();
            },
        ]);
        self::queue($maximumHandler)->consume(
            new MessageSubscription('events', 21, 11, 43201),
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::RETRY,
        );

        $fractionalHandler = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame(1, $command['WaitTimeSeconds']);

                return new Result();
            },
        ]);
        self::queue($fractionalHandler)->consume(
            new MessageSubscription('events', 1.9),
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::RETRY,
        );
    }

    #[Test]
    public function itLogsHandlerFailuresAndLeavesRetryableMessagesOnTheQueue(): void
    {
        $handler = new MockHandler([
            new Result([
                'Messages' => [
                    ['Body' => '{', 'MessageId' => 'first'],
                    ['Body' => '1', 'MessageId' => 'second'],
                ],
            ]),
        ]);
        $failure = new RuntimeException('Handler failed.');
        $logger  = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'SQS message handling failed. The message remains available for retry.',
                self::callback(static function (array $context) use ($failure): bool {
                    self::assertSame(RuntimeException::class, $context['exception']);
                    self::assertSame('Handler failed.', $context['message']);
                    self::assertSame('first', $context['message_id']);
                    self::assertSame('events', $context['queue_name']);
                    self::assertSame($failure->getFile(), $context['file']);
                    self::assertSame($failure->getLine(), $context['line']);
                    self::assertSame($failure->getTraceAsString(), $context['trace']);
                    self::assertArrayNotHasKey('message_body', $context);
                    self::assertArrayNotHasKey('receipt_handle', $context);

                    return true;
                }),
            );
        $calls = 0;
        $queue = self::queue($handler, [], $logger);

        $queue->consume(new MessageSubscription('events'), static function (Message $message) use (&$calls, $failure): QueueMessageHandlingOutcome {
            ++$calls;
            if ($calls === 1) {
                self::assertSame('{', $message->body());

                throw $failure;
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
        $queue   = self::queue($handler);

        $this->expectException(UnexpectedValueException::class);

        $queue->consume(
            new MessageSubscription('events'),
            static fn (): QueueMessageHandlingOutcome => QueueMessageHandlingOutcome::ACKNOWLEDGE,
        );
    }

    #[Test]
    public function itResolvesTheQueueUrlFromTheQueueName(): void
    {
        $handler = new MockHandler([
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
        $queue   = self::queue($handler, ['queueUrl' => '']);

        self::assertSame(
            'message-id',
            $queue->publish(new Message('Event'))->messageId(),
        );
    }

    #[Test]
    public function itRejectsAnUnresolvedQueueUrl(): void
    {
        $queue = self::queue(new MockHandler([new Result()]), ['queueUrl' => '']);

        $this->expectException(UnexpectedValueException::class);

        $queue->publish(new Message('Event'));
    }

    #[Test]
    public function itRequiresAQueueNameWhenResolvingAUrl(): void
    {
        $queue = self::queue(new MockHandler(), ['queue' => '', 'queueUrl' => '']);

        $this->expectException(UnexpectedValueException::class);

        $queue->publish(new Message('Event'));
    }

    /** @param array<string, bool|int|string> $overrides */
    private static function settings(array $overrides = []): SqsSettings
    {
        $values = [
            'continuous' => false,
            'maxNumberOfMessages' => 10,
            'queue' => 'events',
            'queueUrl' => self::QUEUE_URL,
            'visibilityTimeout' => 30,
            'waitTimeSeconds' => 20,
        ];

        return new SqsSettings(ValidatedAwsSettings::sqs([...$values, ...$overrides]));
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

    /** @param array<string, bool|int|string> $overrides */
    private static function queue(
        MockHandler $handler,
        array $overrides = [],
        LoggerInterface|null $logger = null,
    ): SqsQueue {
        $logger ??= new NullLogger();

        return new SqsQueue(
            new SqsTransport(self::sqsClient($handler), $logger),
            self::settings($overrides),
        );
    }

    /** @param Result<mixed> $result */
    private static function malformedQueue(Result $result): SqsQueue
    {
        return new SqsQueue(
            new SqsTransport(new MalformedSqsClient($result), new NullLogger()),
            self::settings(),
        );
    }
}
