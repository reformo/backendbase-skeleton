<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Security;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Paths;
use PHPUnit\Framework\TestCase;
use Tests\Support\Http\ApiResponseAssertions;

use function json_decode;
use function json_encode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

final class SecurityContractTest extends TestCase
{
    public function testSecurityRequirementsRequireBothCredentialsOnProtectedOperations(): void
    {
        $document = ApiResponseAssertions::document('resources/api-docs/example-api/example-openapi.yml');
        $paths    = $document->paths;
        self::assertInstanceOf(Paths::class, $paths);
        foreach ($paths->getPaths() as $path => $pathItem) {
            $this->assertOperationSecurity($path, $pathItem->getOperations());
        }
    }

    /** @param array<string, Operation> $operations */
    private function assertOperationSecurity(string $path, array $operations): void
    {
        foreach ($operations as $operation) {
            $description = json_decode(json_encode($operation->getSerializableData(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $security    = [['apiKey' => []]];
            if ($operation->tags === ['Identity'] || ($operation->tags === ['Example'] && $operation->operationId !== 'getExampleByCriteria')) {
                $security[0]['bearerToken'] = [];
            }

            if (str_starts_with($path, '/_status')) {
                $security = [];
            }

            self::assertEquals($security, $description['security'], $operation->operationId);
            $this->assertIdentitySource($path, $description);
        }
    }

    /** @param array<string, mixed> $operation */
    private function assertIdentitySource(string $path, array $operation): void
    {
        if (! str_starts_with($path, '/auth/') && ! str_starts_with($path, '/identity')) {
            return;
        }

        foreach ($operation['parameters'] as $parameter) {
            if (! (($parameter['name'] ?? null) === 'X-Source-Id')) {
                continue;
            }

            self::assertSame(['example'], $parameter['schema']['enum']);
        }
    }
}
