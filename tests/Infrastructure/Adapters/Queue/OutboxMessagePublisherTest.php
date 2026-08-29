<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Infrastructure\Adapters\Queue\OutboxMessagePublisher;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Operation\MessagePublicationResult;
use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OutboxMessagePublisherTest extends TestCase
{
    #[Test]
    public function itMapsAndPublishesAClaimedOutboxMessage(): void
    {
        $publisher = $this->createMock(MessagePublisher::class);
        $publisher->expects(self::once())
            ->method('publish')
            ->with(new Message(
                'Example_Removed',
                ['exampleId' => 'example-id'],
                'message-id',
                '1.0',
            ))
            ->willReturn(new MessagePublicationResult('transport-id'));
        $adapter = new OutboxMessagePublisher($publisher, new Logger('outbox-publisher-test'));

        $result = $adapter->publish($this->message('{"exampleId":"example-id"}'));

        self::assertTrue($result->isSuccessful());
    }

    #[Test]
    public function itReturnsFailureAndLogsInvalidOrUnpublishedMessages(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('outbox-publisher-test');
        $logger->pushHandler($handler);
        $publisher = $this->createStub(MessagePublisher::class);
        $publisher->method('publish')->willThrowException(new RuntimeException('Broker unavailable.'));
        $adapter = new OutboxMessagePublisher($publisher, $logger);

        self::assertFalse($adapter->publish($this->message('1'))->isSuccessful());
        self::assertFalse($adapter->publish($this->message('{"exampleId":"example-id"}'))->isSuccessful());
        self::assertTrue($handler->hasErrorThatContains('Outbox message publication failed.'));
    }

    private function message(string $payload): ClaimedOutboxMessage
    {
        return new ClaimedOutboxMessage(
            'message-id',
            'Example_Removed',
            '1.0',
            $payload,
            0,
            'claim-token',
        );
    }
}
