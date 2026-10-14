<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Storage;

use Aws\Exception\MultipartUploadException;
use Backendbase\Infrastructure\Adapters\S3Bucket;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_unique;
use function array_values;
use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class S3MultipartUploadTest extends TestCase
{
    #[Test]
    public function itRetriesTransientPartsWithTheBoundedSdkPolicy(): void
    {
        $path     = self::file();
        $attempts = [];
        try {
            $bucket = new S3Bucket(S3ClientFixture::multipartClient(1, $attempts), 'bucket', null);
            self::assertIsString($bucket->putFile($path, 'file.bin'));
            self::assertNotEmpty($attempts);
            self::assertSame([2], array_values(array_unique($attempts)));
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function itStopsAfterTransientPartRetryExhaustion(): void
    {
        $path     = self::file();
        $attempts = [];
        try {
            $bucket = new S3Bucket(S3ClientFixture::multipartClient(10, $attempts), 'bucket', null);
            $bucket->putFile($path, 'file.bin');
            self::fail('The failed upload must stop.');
        } catch (MultipartUploadException) {
            self::assertNotEmpty($attempts);
            self::assertSame([3], array_values(array_unique($attempts)));
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function itDoesNotRepeatPermanentlyDeniedParts(): void
    {
        $path     = self::file();
        $attempts = [];
        try {
            $bucket = new S3Bucket(S3ClientFixture::multipartClient(10, $attempts, 403), 'bucket', null);
            $bucket->putFile($path, 'file.bin');
            self::fail('Access denial must stop the upload.');
        } catch (MultipartUploadException) {
            self::assertNotEmpty($attempts);
            self::assertSame([1], array_values(array_unique($attempts)));
        } finally {
            unlink($path);
        }
    }

    private static function file(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'backendbase-s3-multipart-');
        self::assertIsString($path);
        file_put_contents($path, str_repeat('a', 17 * 1024 * 1024));

        return $path;
    }
}
