<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Health;

use Aws\S3\S3ClientInterface;
use Backendbase\Shared\Health\ReadinessCheck;
use Override;
use UnexpectedValueException;

final readonly class ObjectStoreReadinessCheck implements ReadinessCheck
{
    public function __construct(private S3ClientInterface $client, private string $bucketName)
    {
    }

    /** @return non-empty-string */
    #[Override]
    public function name(): string
    {
        return 'objectStore';
    }

    #[Override]
    public function check(): void
    {
        $result     = $this->client->headBucket(['Bucket' => $this->bucketName]);
        $statusCode = $result['@metadata']['statusCode'] ?? null;
        if ($statusCode !== 200) {
            throw new UnexpectedValueException('The object-store readiness response was not successful.');
        }
    }
}
