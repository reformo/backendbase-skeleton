<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Notification\Batch;

use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Operation\NotificationBatchFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class NotificationBatchStateTest extends TestCase
{
    /** @param list<string> $expectedCompleted */
    #[DataProvider('failedPositions')]
    public function testFirstFailureStopsLaterDeliveryAndRetainsEarlierResults(int $failedIndex, array $expectedCompleted): void
    {
        $calls    = 0;
        $failure  = new RuntimeException('Provider failure.');
        $provider = $this->createStub(NotificationProvider::class);
        $type     = $provider->method('type');
        $type->willReturn('sms');
        $notify = $provider->method('notify');
        $notify->willReturnCallback(static function () use (&$calls, $failedIndex, $failure): NotificationResult {
            $index = $calls++;
            if ($index === $failedIndex) {
                throw $failure;
            }

            return NotificationResult::delivered('sms', 'message-' . $index);
        });
        $notifier = new StackNotifier(new NullLogger());
        $notifier->add($provider);
        $stack = new StackNotification();
        $stack->addNotification(new SmsNotification('+905551112233', 'First'));
        $stack->addNotification(new SmsNotification('+905551112233', 'Second'));
        $stack->addNotification(new SmsNotification('+905551112233', 'Third'));
        try {
            $notifier->notify($stack);
            self::fail('The provider failure must stop the batch.');
        } catch (NotificationBatchFailed $exception) {
            self::assertSame($failedIndex, $exception->failedIndex());
            $completed = $exception->completed();
            self::assertSame($expectedCompleted, $completed->messageIds('sms'));
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertSame($failedIndex + 1, $calls);
    }

    /** @return iterable<string, array{int, list<string>}> */
    public static function failedPositions(): iterable
    {
        yield 'first item' => [0, []];
        yield 'second item' => [1, ['message-0']];
    }
}
