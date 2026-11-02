<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ\RabbitMQConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RabbitMQChannelStateTest extends TestCase
{
    public function testOpenReuseClosedReplacementAndCloseOrder(): void
    {
        $first  = $this->createMock(AMQPChannel::class);
        $second = $this->createMock(AMQPChannel::class);
        $open   = $first->method('is_open');
        $open->willReturnOnConsecutiveCalls(true, false);
        $secondOpen = $second->method('is_open');
        $secondOpen->willReturn(true);
        $firstConfirm = $first->expects(self::once());
        $firstConfirm->method('confirm_select');
        $secondConfirm = $second->expects(self::once());
        $secondConfirm->method('confirm_select');
        $events       = [];
        $channelClose = $second->expects(self::once());
        $closeMethod  = $channelClose->method('close');
        $closeMethod->willReturnCallback(static function () use (&$events): void {
            $events[] = 'channel';
        });
        $connection = $this->createMock(AbstractConnection::class);
        $channels   = $connection->expects(self::exactly(2));
        $channel    = $channels->method('channel');
        $channel->willReturnOnConsecutiveCalls($first, $second);
        $connected = $connection->method('isConnected');
        $connected->willReturn(true);
        $connectionClose = $connection->expects(self::once());
        $closeConnection = $connectionClose->method('close');
        $closeConnection->willReturnCallback(static function () use (&$events): void {
            $events[] = 'connection';
        });
        $lifecycle = new RabbitMQConnection($connection);

        $initial     = $lifecycle->channel();
        $reused      = $lifecycle->channel();
        $replacement = $lifecycle->channel();
        self::assertSame($first, $initial);
        self::assertSame($first, $reused);
        self::assertSame($second, $replacement);
        $lifecycle->close();
        self::assertSame(['channel', 'connection'], $events);
    }

    public function testChannelCreationFailureCanBeTriedAgain(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $confirm = $channel->expects(self::once());
        $confirm->method('confirm_select');
        $connection = $this->createMock(AbstractConnection::class);
        $calls      = 0;
        $opening    = $connection->expects(self::exactly(2));
        $method     = $opening->method('channel');
        $method->willReturnCallback(static function () use (&$calls, $channel): AMQPChannel {
            $calls++;
            if ($calls === 1) {
                throw new RuntimeException('Channel creation failed.');
            }

            return $channel;
        });
        $lifecycle = new RabbitMQConnection($connection);
        try {
            $lifecycle->channel();
            self::fail('The first channel creation must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Channel creation failed.', $exception->getMessage());
        }

        self::assertSame($channel, $lifecycle->channel());
        self::assertSame(2, $calls);
    }

    public function testAnOpenChannelIsReusedAfterConfirmSelectionFails(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $open    = $channel->method('is_open');
        $open->willReturn(true);
        $selection = $channel->expects(self::once());
        $confirm   = $selection->method('confirm_select');
        $confirm->willThrowException(new RuntimeException('Confirm selection failed.'));
        $connection = $this->createMock(AbstractConnection::class);
        $opening    = $connection->expects(self::once());
        $method     = $opening->method('channel');
        $method->willReturn($channel);
        $lifecycle = new RabbitMQConnection($connection);
        try {
            $lifecycle->channel();
            self::fail('Confirm selection must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Confirm selection failed.', $exception->getMessage());
        }

        self::assertSame($channel, $lifecycle->channel());
    }

    public function testChannelCloseFailurePropagatesBeforeConnectionClose(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $open    = $channel->method('is_open');
        $open->willReturn(true);
        $failure       = new RuntimeException('Channel close failed.');
        $closing       = $channel->expects(self::once());
        $closingMethod = $closing->method('close');
        $closingMethod->willThrowException($failure);
        $connection = $this->createMock(AbstractConnection::class);
        $opening    = $connection->method('channel');
        $opening->willReturn($channel);
        $connectionClosing = $connection->expects(self::never());
        $connectionClosing->method('close');
        $lifecycle = new RabbitMQConnection($connection);
        $lifecycle->channel();

        $this->expectExceptionObject($failure);
        $lifecycle->close();
    }
}
