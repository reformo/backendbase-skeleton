<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Storage;

use Aws\MockHandler;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function is_string;
use function parse_str;

final class S3ClientFixture
{
    public static function client(MockHandler $handler): S3Client
    {
        return new S3Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler' => $handler,
            'region' => 'eu-central-1',
            'retries' => 2,
            'version' => 'latest',
        ]);
    }

    /** @param array<string, int> $partAttempts */
    public static function multipartClient(int $failedAttempts, array &$partAttempts, int $failureStatus = 500): S3Client
    {
        return new S3Client([
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'region' => 'eu-central-1',
            'retries' => 2,
            'version' => 'latest',
            'http_handler' => static function (RequestInterface $request) use ($failedAttempts, &$partAttempts, $failureStatus): PromiseInterface {
                $uri         = $request->getUri();
                $queryString = $uri->getQuery();
                $query       = [];
                parse_str($queryString, $query);
                if (isset($query['uploads'])) {
                    return Create::promiseFor(new Response(200, [], '<InitiateMultipartUploadResult><Bucket>bucket</Bucket><Key>file.bin</Key><UploadId>upload-id</UploadId></InitiateMultipartUploadResult>'));
                }

                if ($request->getMethod() === 'POST') {
                    return Create::promiseFor(new Response(200, [], '<CompleteMultipartUploadResult><Location>https://bucket.example.com/file.bin</Location><Bucket>bucket</Bucket><Key>file.bin</Key><ETag>etag</ETag></CompleteMultipartUploadResult>'));
                }

                $number = $query['partNumber'] ?? null;
                if (! is_string($number)) {
                    throw new RuntimeException('The multipart request must contain a part number.');
                }

                $partAttempts[$number] = ($partAttempts[$number] ?? 0) + 1;
                if ($partAttempts[$number] <= $failedAttempts) {
                    $code = $failureStatus === 403 ? 'AccessDenied' : 'InternalError';

                    return Create::rejectionFor([
                        'exception' => new RuntimeException('Upload failed.'),
                        'response' => new Response($failureStatus, [], '<Error><Code>' . $code . '</Code><Message>Upload failed.</Message></Error>'),
                    ]);
                }

                return Create::promiseFor(new Response(200, ['ETag' => 'etag-' . $number]));
            },
        ]);
    }
}
