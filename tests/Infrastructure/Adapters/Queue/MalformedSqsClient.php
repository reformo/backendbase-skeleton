<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Aws\Result;
use Aws\Sqs\SqsClient;

final class MalformedSqsClient extends SqsClient
{
    /** @param Result<mixed> $result */
    public function __construct(private readonly Result $result)
    {
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return Result<mixed>
     */
    public function receiveMessage(array $arguments = []): Result
    {
        return $this->result;
    }
}
