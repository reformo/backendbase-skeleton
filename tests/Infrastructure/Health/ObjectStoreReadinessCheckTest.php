<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Health;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Backendbase\Infrastructure\Health\ObjectStoreReadinessCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ObjectStoreReadinessCheckTest extends TestCase
{
    #[Test]
    public function itChecksTheConfiguredBucket(): void
    {
        $handler = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame('HeadBucket', $command->getName());
                self::assertSame('assets', $command['Bucket']);

                return new Result(['@metadata' => ['statusCode' => 200]]);
            },
        ]);

        $check = new ObjectStoreReadinessCheck(self::client($handler), 'assets');

        self::assertSame('objectStore', $check->name());
        $check->check();
    }

    #[Test]
    public function itRejectsAnInvalidObjectStoreResponse(): void
    {
        $check = new ObjectStoreReadinessCheck(
            self::client(new MockHandler([new Result(['@metadata' => ['statusCode' => 201]])])),
            'assets',
        );

        $this->expectException(UnexpectedValueException::class);

        $check->check();
    }

    private static function client(MockHandler $handler): S3Client
    {
        return new S3Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }
}
