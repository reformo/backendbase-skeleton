<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\Idempotency;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Support\Http\ApiResponseAssertions;

use function array_column;
use function glob;
use function in_array;

final class IdempotencyContractTest extends TestCase
{
    #[DataProvider('apis')]
    public function testContractsAndMaintainedClientsRequireKeysOnlyForWrites(string $slug, string $root): void
    {
        $document = ApiResponseAssertions::document('resources/api-docs/' . $slug . '/' . $root);
        $paths    = $document->paths;
        self::assertInstanceOf(Paths::class, $paths);
        foreach ($paths->getPaths() as $path) {
            $this->assertPath($path);
        }

        $configuration = require 'config/' . $slug . '/http-headers-global.php';
        self::assertStringContainsString('Idempotency-Key', $configuration['headers']['Access-Control-Allow-Headers']);
        foreach (glob('resources/bruno/' . $slug . '/*/*.yml') ?: [] as $file) {
            $this->assertBruno($file);
        }
    }

    public function testAllPublicAuthenticationSurfacesEnforceTheHeaderAtRuntime(): void
    {
        $fixture = ExampleApiFixture::identity();
        foreach ([IdentityApiFixture::example($fixture), IdentityApiFixture::create($fixture, Realm::USER), IdentityApiFixture::create($fixture, Realm::ADMIN)] as $api) {
            $request  = new ServerRequest(uri: '/auth/login', method: 'POST', headers: ['Backendbase-Api-Key' => 'example-api-key']);
            $response = $api->handle($request);
            self::assertSame(400, $response->getStatusCode());
            self::assertStringContainsString('Idempotency-Key', (string) $response->getBody());
        }
    }

    private function assertPath(PathItem $path): void
    {
        foreach ($path->getOperations() as $method => $operation) {
            $this->assertOperation($method, $operation);
        }
    }

    private function assertOperation(string $method, Operation $operation): void
    {
        $headers = [];
        foreach ($operation->parameters as $parameter) {
            self::assertInstanceOf(Parameter::class, $parameter);
            $headers[$parameter->name] = $parameter;
        }

        if (! in_array($method, ['post', 'put', 'patch', 'delete'], true)) {
            self::assertArrayNotHasKey('Idempotency-Key', $headers);

            return;
        }

        self::assertArrayHasKey('Idempotency-Key', $headers);
        self::assertTrue($headers['Idempotency-Key']->required);
        $responses = $operation->responses;
        self::assertNotNull($responses);
        foreach (['400', '409', '422', '500', '503'] as $status) {
            self::assertNotNull($responses->getResponse($status));
        }
    }

    private function assertBruno(string $file): void
    {
        $request = Yaml::parseFile($file);
        self::assertIsArray($request);
        if (! isset($request['http']) || ! in_array($request['http']['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $headers = array_column($request['http']['headers'], 'value', 'name');
        self::assertSame('{{$randomUUID}}', $headers['Idempotency-Key']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function apis(): iterable
    {
        yield 'example' => ['example-api', 'example-openapi.yml'];
        yield 'user' => ['user-api', 'user-api-openapi.yml'];
        yield 'admin' => ['system-admin-api', 'system-admin-api-openapi.yml'];
    }
}
