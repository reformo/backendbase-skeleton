<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Backendbase\Infrastructure\Adapters\S3Bucket;
use Backendbase\Shared\Exception\InvalidUserInput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_fill;
use function file_put_contents;
use function restore_error_handler;
use function set_error_handler;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class S3BucketTest extends TestCase
{
    #[Test]
    public function itCreatesCloudFrontAndS3Urls(): void
    {
        $client           = self::client(new MockHandler());
        $cloudFrontBucket = new S3Bucket($client, 'bucket', 'https://cdn.example.com/');
        self::assertNull($cloudFrontBucket->getPreSignedUrl(null, 60));
        self::assertSame(
            'https://cdn.example.com/images/example.jpg',
            $cloudFrontBucket->getPreSignedUrl('images/example.jpg', 60),
        );

        $s3Bucket = new S3Bucket($client, 'bucket', null);
        $url      = $s3Bucket->getPreSignedUrl('images/example.jpg', 60);
        self::assertIsString($url);
        self::assertStringContainsString('images/example.jpg', $url);
        self::assertStringContainsString('X-Amz-Signature=', $url);

        $withoutCdn = new S3Bucket($client, 'bucket', '');
        $signedUrl  = $withoutCdn->getPreSignedUrl('images/example.jpg', 60);
        self::assertIsString($signedUrl);
        self::assertStringContainsString('X-Amz-Signature=', $signedUrl);
    }

    #[Test]
    public function itUploadsAFileAndReturnsItsObjectUrl(): void
    {
        $handler = new MockHandler([
            static function (CommandInterface $command): Result {
                self::assertSame('PutObject', $command->getName());
                self::assertSame('bucket', $command['Bucket']);
                self::assertSame('documents/example.txt', $command['Key']);

                return new Result([
                    '@metadata' => ['statusCode' => 200],
                ]);
            },
        ]);
        $path    = tempnam(sys_get_temp_dir(), 'backendbase-s3-');
        self::assertIsString($path);
        file_put_contents($path, 'contents');

        try {
            $bucket = new S3Bucket(self::client($handler), 'bucket', null);
            self::assertSame(
                'https://bucket.s3.eu-central-1.amazonaws.com/documents/example.txt',
                $bucket->putFile($path, 'documents/example.txt'),
            );
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function itCreatesAPresignedPostRequest(): void
    {
        $bucket  = new S3Bucket(self::client(new MockHandler()), 'bucket', null);
        $request = $bucket->createSignedRequest('images/example.jpg', 'image/jpeg', 300);

        self::assertArrayHasKey('formAttributes', $request);
        self::assertArrayHasKey('formInputs', $request);
        self::assertSame('images/example.jpg', $request['formInputs']['key']);
        self::assertSame('private', $request['formInputs']['acl']);

        $this->expectException(InvalidUserInput::class);
        $bucket->createSignedRequest('', 'image/jpeg', 300);
    }

    #[Test]
    public function itReturnsNullWhenS3DoesNotAcceptTheUpload(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backendbase-s3-');
        self::assertIsString($path);
        file_put_contents($path, 'contents');
        $handler = new MockHandler([
            new Result(['@metadata' => ['statusCode' => 201]]),
        ]);

        try {
            $bucket = new S3Bucket(self::client($handler), 'bucket', null);
            self::assertNull($bucket->putFile($path, 'documents/example.txt'));
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function itRejectsAnUnreadableLocalFile(): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $bucket = new S3Bucket(self::client(new MockHandler()), 'bucket', null);

            $this->expectException(RuntimeException::class);

            $bucket->putFile(sys_get_temp_dir() . '/missing-backendbase-file', 'missing');
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function itResumesAFailedMultipartUpload(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backendbase-s3-multipart-');
        self::assertIsString($path);
        file_put_contents($path, str_repeat('a', 17 * 1024 * 1024));
        $firstPartFailed = false;
        $responder       = static function (CommandInterface $command) use (&$firstPartFailed): Result|AwsException {
            if ($command->getName() === 'CreateMultipartUpload') {
                return new Result(['UploadId' => 'upload-id']);
            }

            if ($command->getName() === 'UploadPart' && ! $firstPartFailed) {
                $firstPartFailed = true;

                return new AwsException('Temporary upload failure.', $command);
            }

            if ($command->getName() === 'UploadPart') {
                return new Result(['ETag' => 'etag-' . $command['PartNumber']]);
            }

            return new Result([
                '@metadata' => ['statusCode' => 200],
                'ObjectURL' => 'https://bucket.example.com/documents/example.bin',
            ]);
        };
        $handler         = new MockHandler(array_fill(0, 20, $responder));

        try {
            $bucket = new S3Bucket(self::client($handler), 'bucket', null);
            self::assertIsString($bucket->putFile($path, 'documents/example.bin'));
            self::assertTrue($firstPartFailed);
            self::assertSame('CompleteMultipartUpload', $handler->getLastCommand()->getName());
        } finally {
            unlink($path);
        }
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
