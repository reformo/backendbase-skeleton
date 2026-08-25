<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;
use Aws\S3\PostObjectV4;
use Aws\S3\S3ClientInterface;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Integrations\BucketService;
use Override;
use RuntimeException;

use function basename;
use function dirname;
use function fclose;
use function fopen;
use function rewind;

class S3Bucket implements BucketService
{
    public function __construct(private readonly S3ClientInterface $s3Client, private readonly string $bucketName, private readonly string|null $cloudFront)
    {
    }

    #[Override]
    public function getPreSignedUrl(string|null $key, int $expiresInSeconds): string|null
    {
        if (empty($key)) {
            return null;
        }

        if ($this->cloudFront !== null) {
            return $this->cloudFront . $key;
        }

        $cmd = $this->s3Client
            ->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key' => $key,

            ]);

        return (string) $this->s3Client
            ->createPresignedRequest($cmd, '+' . $expiresInSeconds . ' seconds', ['response-content-type' => 'image/jpeg'])
            ->getUri();
    }

    #[Override]
    public function putFile(string $localFile, string $key): string|null
    {
        $source = fopen($localFile, 'rb');
        if ($source === false) {
            throw new RuntimeException('Could not open the local file for upload.');
        }

        $uploader  = new ObjectUploader(
            $this->s3Client,
            $this->bucketName,
            $key,
            $source,
        );
        $objectUrl = null;
        do {
            try {
                $result = $uploader->upload();
                if ((int) $result['@metadata']['statusCode'] === 200) {
                    $objectUrl = $result['ObjectURL'];
                }
            } catch (MultipartUploadException $e) {
                rewind($source);
                $uploader = new MultipartUploader($this->s3Client, $source, [
                    'state' => $e->getState(),
                ]);
            }
        } while (! isset($result));

        fclose($source);

        return $objectUrl;
    }

    /** @return array<string, mixed> */
    #[Override]
    public function createSignedRequest(string $key, string $contentType, int $expiresInSeconds): array
    {
        if (empty($key)) {
            throw InvalidUserInput::create('File name is not provided or empty');
        }

        $prefix     = dirname($key) . '/';
        $filename   = basename($key);
        $formInputs = [
            'acl' => 'private',
            'key' => $prefix . $filename,
        ];

        $options    = [
            ['acl' => 'private'],
            ['bucket' => $this->bucketName],
            ['starts-with', '$key', $prefix],
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
}
