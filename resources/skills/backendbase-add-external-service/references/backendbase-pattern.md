# Backendbase external-service pattern

## Dependency direction

```text
application use case -> project port -> infrastructure adapter -> vendor client
vendor response -> adapter validation/mapping -> project result
```

| Role | Backendbase evidence | Target decision |
| --- | --- | --- |
| Project port | `BucketService` and `Notify` hide SDKs | Prefer a narrower context-owned name for a general service |
| Adapter | `S3Bucket` and `SnsNotifier` map project values to AWS | Map the requested vendor action only |
| Configuration | Autoload files define settings structure | Validate target values and types |
| Composition | `config/dependencies/aws.php` constructs clients and adapters | Use target container conventions |
| Tests | AWS mock handlers and interface doubles | Keep all calls deterministic |

A narrow port uses project terms:

```php
interface AddressRiskLookup
{
    public function assess(Address $address): RiskAssessment;
}
```

Do not return an SDK response or accept an SDK request object. Put vendor-specific serialization, headers, pagination tokens, and errors in the adapter.

## Failure policy

- Reject invalid local input before the call.
- Reject incomplete or malformed successful responses.
- Retry selected connection failures, timeouts, rate limits, and server failures only when safe.
- Do not retry denied, missing, or contract-invalid requests without a state change.
- Preserve unknown external outcomes for operator review when a state-changing call might have succeeded.

## Current source limitations

- No populated general vendor-service subtree exists; object storage and notifications are the live adapter examples.
- S3 composition differs from the shared AWS client helper. It uses explicit credentials and path-style requests for a custom endpoint.
- Some current adapters expose weak response validation or sensitive debug payloads. Do not copy those gaps.

## Exact source provenance

- `src/Backendbase/Shared/Integrations/BucketService.php`
- `src/Backendbase/Infrastructure/Adapters/S3Bucket.php`
- `src/Backendbase/Shared/Integrations/Notify.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/SnsNotifier.php`
- `config/autoload/object-store.global.php`
- `config/autoload/aws.global.php`
- `config/dependencies/aws.php`
- `tests/Infrastructure/Adapters/S3BucketTest.php`
- `tests/Infrastructure/Adapters/Notification/SnsNotifierTest.php`
- `tests/Infrastructure/Adapters/AwsDependencyDefinitionsTest.php`
- `resources/docs/14-external-services-and-object-storage.html`
- `resources/platform/19-external-services.md`

Use the architecture and failure decisions without copying AWS-specific values into another provider.
