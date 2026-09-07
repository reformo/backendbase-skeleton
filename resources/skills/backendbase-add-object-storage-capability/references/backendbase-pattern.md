# Backendbase object-storage pattern

## Port and adapter roles

`BucketService` exposes three project operations:

```php
interface BucketService
{
    public function getPreSignedUrl(?string $key, int $expiresInSeconds): ?string;
    public function putFile(string $localFile, string $key): ?string;
    public function createSignedRequest(string $key, string $contentType, int $expiresInSeconds): array;
}
```

The S3 adapter owns `S3ClientInterface`, commands, presigned requests, upload streams, multipart recovery, and signed POST construction. Project callers see strings and arrays, not SDK objects.

| Concern | Backendbase behavior | Safer target decision |
| --- | --- | --- |
| Downloads | CDN URL when configured, otherwise signed S3 request | Normalize base URL and validate expiration |
| Server upload | Stream plus `ObjectUploader`; resume multipart | Bound attempts and close resources on every path |
| Browser upload | Private signed POST for one key prefix | Enforce type, size, key, and expiration policy |
| Composition | Object-store config creates S3 client and adapter | Validate settings and support deployment credential policy |
| Tests | AWS `MockHandler` checks commands and results | Cover failure and exhaustion paths without network |

## Current source limitations

- Multipart recovery has no explicit attempt limit.
- Signed POST accepts `contentType` but does not include it in policy.
- Signed download forces `image/jpeg`.
- Object-store settings use `OBJECT_STORE_ENDPOINT`, or use `AWS_ENDPOINT` when the object-store value is unset or empty.
- S3 composition always creates explicit credentials, including empty values, instead of using the shared AWS credential-chain behavior.
- Do not preserve these behaviors unless the target contract explicitly requires them.

## Exact source provenance

- `src/Backendbase/Shared/Integrations/BucketService.php`
- `src/Backendbase/Infrastructure/Adapters/S3Bucket.php`
- `config/autoload/object-store.global.php`
- `config/dependencies/aws.php`
- `tests/Infrastructure/Adapters/S3BucketTest.php`
- `tests/Infrastructure/Adapters/AwsDependencyDefinitionsTest.php`
- `resources/platform/19-external-services.md`

The target provider controls exact request fields and capabilities.
