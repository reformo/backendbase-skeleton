<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Http\ApiResponseAssertions;

use function array_keys;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SuccessResponseContractTest extends TestCase
{
    private const array RESPONSE_KEYS = [
        '/auth/register' => 'registration',
        '/auth/ownership/verify' => 'ownershipVerification',
        '/auth/ownership/resend' => 'challenge',
        '/auth/mfa/enrollment' => 'enrollment',
        '/auth/mfa/enrollment/complete' => 'enrollment',
        '/auth/login' => 'challenge',
        '/auth/login/complete' => 'session',
        '/identity/token' => 'token',
        '/identity/session' => 'session',
        '/identity/account' => 'account',
        '/administration/admins/invite' => 'invitation',
        '/administration/admins/approve' => 'admin',
        '/administration/actors/change' => 'actor',
        '/administration/actors/{realm}/{actor-id}' => 'actor',
    ];

    #[DataProvider('documents')]
    public function testSourceAndMergedSuccessSchemasUseDescriptiveKeys(string $file, int $expectedEnvelopes, string $identityKey): void
    {
        $specification = ApiResponseAssertions::document($file);
        $document      = json_decode(json_encode($specification->getSerializableData(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertIsArray($document['paths']);
        $envelopes = 0;
        foreach ($document['paths'] as $path => $operations) {
            self::assertIsString($path);
            self::assertIsArray($operations);
            $responseKey = $path === '/identity' ? $identityKey : self::RESPONSE_KEYS[$path] ?? null;
            $envelopes  += $this->assertOperations($operations, $responseKey);
        }

        self::assertSame($expectedEnvelopes, $envelopes);
        $problem = $document['components']['schemas']['Problem'];
        self::assertIsArray($problem);
        self::assertContains('status', $problem['required']);
        self::assertSame('integer', $problem['properties']['status']['type']);
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function documents(): iterable
    {
        yield 'user source' => ['resources/api-docs/user-api/user-api-openapi.yml', 11, 'user'];
        yield 'user merged' => ['public/user-api/docs/user-api-merged.yml', 11, 'user'];
        yield 'admin source' => ['resources/api-docs/system-admin-api/system-admin-api-openapi.yml', 14, 'admin'];
        yield 'admin merged' => ['public/system-admin-api/docs/system-admin-api-merged.yml', 14, 'admin'];
    }

    /** @param array<array-key, mixed> $operations */
    private function assertOperations(array $operations, string|null $responseKey): int
    {
        $envelopes = 0;
        foreach ($operations as $operation) {
            self::assertIsArray($operation);
            self::assertIsArray($operation['responses']);
            $envelopes += $this->assertResponses($operation['responses'], $responseKey);
        }

        return $envelopes;
    }

    /** @param array<int|string, mixed> $responses */
    private function assertResponses(array $responses, string|null $responseKey): int
    {
        $envelopes = 0;
        foreach ($responses as $status => $response) {
            self::assertIsArray($response);
            $envelopes += $this->assertSuccessSchema($status, $response, $responseKey);
        }

        return $envelopes;
    }

    /** @param array<array-key, mixed> $response */
    private function assertSuccessSchema(int|string $status, array $response, string|null $responseKey): int
    {
        if ((int) $status < 200 || (int) $status >= 300) {
            return 0;
        }

        $schema = $response['content']['application/json']['schema'];
        self::assertIsArray($schema);
        $properties = $schema['properties'] ?? [];
        self::assertIsArray($properties);
        self::assertArrayNotHasKey('statusCode', $properties);
        self::assertArrayNotHasKey('data', $properties);
        if ($responseKey === null) {
            return 0;
        }

        self::assertSame([$responseKey], array_keys($properties));
        self::assertSame([$responseKey], $schema['required']);

        return 1;
    }
}
