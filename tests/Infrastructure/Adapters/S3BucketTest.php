<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Backendbase\Infrastructure\Adapters\S3Bucket;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Infrastructure\Adapters\Storage\S3ClientFixture;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class S3BucketTest extends TestCase
{
    #[Test]
    public function itCreatesCloudFrontAndS3Urls(): void
    {
        $client           = S3ClientFixture::client(new MockHandler());
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
        self::assertStringNotContainsString('response-content-type', $url);

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
            $bucket = new S3Bucket(S3ClientFixture::client($handler), 'bucket', null);
            self::assertSame(
                'https://bucket.s3.eu-central-1.amazonaws.com/documents/example.txt',
                $bucket->putFile($path, 'documents/example.txt'),
            );
        } finally {
            unlink($path);
        }
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
            $bucket = new S3Bucket(S3ClientFixture::client($handler), 'bucket', null);
            self::assertNull($bucket->putFile($path, 'documents/example.txt'));
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function itRejectsAnUnreadableLocalFile(): void
    {
        $bucket = new S3Bucket(S3ClientFixture::client(new MockHandler()), 'bucket', null);
        $this->expectException(RuntimeException::class);

        $bucket->putFile(sys_get_temp_dir() . '/missing-backendbase-file', 'missing');
    }
}
