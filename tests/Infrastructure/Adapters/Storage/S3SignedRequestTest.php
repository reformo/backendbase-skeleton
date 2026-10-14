<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Storage;

use Aws\MockHandler;
use Backendbase\Infrastructure\Adapters\S3Bucket;
use Backendbase\Shared\Exception\InvalidUserInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class S3SignedRequestTest extends TestCase
{
    #[Test]
    public function itLimitsSignedUploadsToTheExactKeyAndContentType(): void
    {
        $bucket  = new S3Bucket(S3ClientFixture::client(new MockHandler()), 'bucket', null);
        $request = $bucket->createSignedRequest('images/example.jpg', 'image/jpeg', 300);
        $policy  = json_decode(base64_decode($request['formInputs']['Policy']), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('formAttributes', $request);
        self::assertSame('images/example.jpg', $request['formInputs']['key']);
        self::assertSame('private', $request['formInputs']['acl']);
        self::assertSame('image/jpeg', $request['formInputs']['Content-Type']);
        self::assertContains(['eq', '$key', 'images/example.jpg'], $policy['conditions']);
        self::assertContains(['eq', '$Content-Type', 'image/jpeg'], $policy['conditions']);
    }

    #[Test]
    #[DataProvider('invalidSigningInputs')]
    public function itRejectsInvalidSigningInputs(string $key, string $contentType, int $expiration): void
    {
        $bucket = new S3Bucket(S3ClientFixture::client(new MockHandler()), 'bucket', null);
        $this->expectException(InvalidUserInput::class);

        $bucket->createSignedRequest($key, $contentType, $expiration);
    }

    /** @return list<array{string, string, int}> */
    public static function invalidSigningInputs(): array
    {
        return [
            ['', 'image/jpeg', 60],
            ['file.txt', '', 60],
            ['file.txt', "text/plain\r\nX-Header: injected", 60],
            ['file.txt', 'text/plain', 0],
            ['file.txt', 'text/plain', 604801],
        ];
    }

    #[Test]
    public function itRejectsUnboundedDownloadExpiration(): void
    {
        $bucket = new S3Bucket(S3ClientFixture::client(new MockHandler()), 'bucket', null);
        $this->expectException(InvalidUserInput::class);

        $bucket->getPreSignedUrl('file.txt', 604801);
    }
}
