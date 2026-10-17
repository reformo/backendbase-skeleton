<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters;

use Aws\CommandInterface;
use Aws\Middleware;
use Aws\ResultInterface;
use Aws\RetryMiddleware;
use Aws\S3\ObjectUploader;
use Aws\S3\PostObjectV4;
use Aws\S3\S3ClientInterface;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Integrations\BucketService;
use GuzzleHttp\Psr7\LazyOpenStream;
use Override;
use RuntimeException;

use function is_file;
use function is_readable;
use function preg_match;

class S3Bucket implements BucketService
{
    public function __construct(private readonly S3ClientInterface $s3Client, private readonly string $bucketName, private readonly string|null $cdnBaseUrl)
    {
    }

    #[Override]
    public function getPreSignedUrl(string|null $key, int $expiresInSeconds): string|null
    {
        if ($key === null || $key === '') {
            return null;
        }

        self::validateExpiration($expiresInSeconds);
        if ($this->cdnBaseUrl !== null && $this->cdnBaseUrl !== '') {
            return $this->cdnBaseUrl . $key;
        }

        $getObjectCommand = $this->s3Client
            ->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key' => $key,

            ]);

        $request = $this->s3Client->createPresignedRequest($getObjectCommand, '+' . $expiresInSeconds . ' seconds');
        $uri     = $request->getUri();

        return (string) $uri;
    }

    #[Override]
    public function putFile(string $localFile, string $key): string|null
    {
        if (! is_file($localFile) || ! is_readable($localFile)) {
            throw new RuntimeException('Could not open the local file for upload.');
        }

        $source = new LazyOpenStream($localFile, 'rb');
        try {
            $uploader = new ObjectUploader($this->s3Client, $this->bucketName, $key, $source, 'private', [
                'before_upload' => self::configurePartRetries(...),
            ]);
            $result   = $uploader->upload();

            return self::uploadUrl($result);
        } finally {
            $source->close();
        }
    }

    /** @return array<string, mixed> */
    #[Override]
    public function createSignedRequest(string $key, string $contentType, int $expiresInSeconds): array
    {
        if ($key === '') {
            throw InvalidUserInput::create('File name is not provided or empty');
        }

        self::validateExpiration($expiresInSeconds);
        if (preg_match('~\A[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+\z~', $contentType) !== 1) {
            throw InvalidUserInput::create('The content type must be a valid media type.');
        }

        $formInputs = [
            'acl' => 'private',
            'key' => $key,
            'Content-Type' => $contentType,
        ];

        $options    = [
            ['acl' => 'private'],
            ['bucket' => $this->bucketName],
            ['eq', '$key', $key],
            ['eq', '$Content-Type', $contentType],
        ];
        $postObject = new PostObjectV4(
            $this->s3Client,
            $this->bucketName,
            $formInputs,
            $options,
            '+' . $expiresInSeconds . ' seconds',
        );

        return [
            'formAttributes' => $postObject->getFormAttributes(),
            'formInputs' => $postObject->getFormInputs(),
        ];
    }

    private static function validateExpiration(int $expiresInSeconds): void
    {
        if ($expiresInSeconds < 1 || $expiresInSeconds > 604800) {
            throw InvalidUserInput::create('The expiration must be between 1 and 604800 seconds.');
        }
    }

    private static function uploadUrl(ResultInterface $result): string|null
    {
        if (($result['@metadata']['statusCode'] ?? null) !== 200) {
            return null;
        }

        return (string) $result['ObjectURL'];
    }

    private static function configurePartRetries(CommandInterface $command): void
    {
        if ($command->getName() !== 'UploadPart') {
            return;
        }

        // Retry part failures before the SDK multipart collector consumes them.
        $handlers   = $command->getHandlerList();
        $decider    = RetryMiddleware::createDefaultDecider(2);
        $middleware = Middleware::retry($decider, [RetryMiddleware::class, 'exponentialDelay']);
        $handlers->appendSign($middleware, 'object-upload-part-retry');
    }
}
