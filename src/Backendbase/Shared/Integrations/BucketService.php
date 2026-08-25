<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

interface BucketService
{
    public function getPreSignedUrl(string|null $key, int $expiresInSeconds): string|null;

    public function putFile(string $localFile, string $key): string|null;

    /** @return array<string, mixed> */
    public function createSignedRequest(string $key, string $contentType, int $expiresInSeconds): array;
}
