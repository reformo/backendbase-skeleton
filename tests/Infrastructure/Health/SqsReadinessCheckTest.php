<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Health;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Configuration\Aws\SqsSettings;
use Backendbase\Infrastructure\Health\SqsReadinessCheck;
use Backendbase\Shared\Configuration\ValidatedAwsSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class SqsReadinessCheckTest extends TestCase
{
    private const string QUEUE_URL = 'https://sqs.eu-central-1.amazonaws.com/123456789012/events';

    #[Test]
    public function itChecksAConfiguredQueueUrl(): void
    {
        $handler = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame('GetQueueAttributes', $command->getName());
                self::assertSame(self::QUEUE_URL, $command['QueueUrl']);

                return new Result(['Attributes' => ['QueueArn' => 'arn:aws:sqs:eu-central-1:123456789012:events']]);
            },
        ]);

        $check = new SqsReadinessCheck(self::client($handler), self::settings(['queueUrl' => self::QUEUE_URL]));

        self::assertSame('queue', $check->name());
        $check->check();
    }

    #[Test]
    public function itResolvesTheQueueUrlBeforeChecking(): void
    {
        $handler = new MockHandler([
            new Result(['QueueUrl' => self::QUEUE_URL]),
            new Result(['Attributes' => ['QueueArn' => 'arn:aws:sqs:eu-central-1:123456789012:events']]),
        ]);

        new SqsReadinessCheck(
            self::client($handler),
            self::settings(['queue' => 'events', 'queueUrl' => '']),
        )->check();

        self::assertCount(0, $handler);
    }

    #[Test]
    public function itRejectsInvalidQueueResponses(): void
    {
        $this->assertCheckFails(new SqsReadinessCheck(self::client(new MockHandler()), self::settings([])));
        $this->assertCheckFails(new SqsReadinessCheck(
            self::client(new MockHandler([new Result()])),
            self::settings(['queue' => 'events']),
        ));
        $this->assertCheckFails(new SqsReadinessCheck(
            self::client(new MockHandler([new Result()])),
            self::settings(['queueUrl' => self::QUEUE_URL]),
        ));
    }

    private function assertCheckFails(SqsReadinessCheck $check): void
    {
        try {
            $check->check();
            self::fail('An invalid SQS readiness response must fail.');
        } catch (UnexpectedValueException) {
            $this->addToAssertionCount(1);
        }
    }

    private static function client(MockHandler $handler): SqsClient
    {
        return new SqsClient([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }

    /** @param array<string, string> $values */
    private static function settings(array $values): SqsSettings
    {
        return new SqsSettings(ValidatedAwsSettings::sqs($values));
    }
}
