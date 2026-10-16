<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\Http\ApiResponseAssertions;

use function glob;
use function json_decode;
use function json_encode;
use function preg_replace;
use function sort;
use function str_replace;
use function str_starts_with;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

final class ApiContractTest extends TestCase
{
    #[DataProvider('realms')]
    public function testRoutesOpenApiAndBrunoHaveTheSamePublicOperations(string $apiName, string $slug): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $app       = AppFactory::create(container: $container);
        $register  = require 'src/Backendbase/Infrastructure/Inbound/' . $apiName . '/routes.php';
        $register($app);
        $collector = $app->getRouteCollector();
        $runtime   = [];
        foreach ($collector->getRoutes() as $route) {
            $controller = $route->getCallable();
            self::assertIsString($controller);
            self::assertStringStartsWith('Backendbase\\Infrastructure\\Inbound\\' . $apiName . '\\Controllers\\', $controller);
            self::assertIsCallable($container->get($controller));
            foreach ($route->getMethods() as $method) {
                if ($method === 'OPTIONS') {
                    continue;
                }

                $runtime[] = $method . ' ' . self::normalize($route->getPattern());
            }
        }

        $document      = ApiResponseAssertions::document('resources/api-docs/' . $slug . '/' . $slug . '-openapi.yml');
        $specification = json_decode(json_encode($document->getSerializableData(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($specification);
        self::assertIsArray($specification['paths']);
        $documented = [];
        foreach ($specification['paths'] as $path => $operations) {
            self::assertIsString($path);
            self::assertIsArray($operations);
            foreach ($operations as $method => $operation) {
                self::assertIsString($method);
                self::assertIsArray($operation);
                $documented[] = strtoupper($method) . ' ' . self::normalize($path);
                self::assertSame(str_starts_with($path, '/identity') || str_starts_with($path, '/administration'), ! empty($operation['security']));
            }
        }

        $examples = [];
        $files    = glob('resources/bruno/' . $slug . '/*/*.yml');
        self::assertIsArray($files);
        foreach ($files as $file) {
            $request = Yaml::parseFile($file);
            self::assertIsArray($request);
            if (! isset($request['http'])) {
                continue;
            }

            self::assertIsArray($request['http']);
            self::assertIsString($request['http']['url']);
            self::assertIsString($request['http']['method']);
            $path       = str_replace('{{baseUrl}}', '', $request['http']['url']);
            $examples[] = $request['http']['method'] . ' ' . self::normalize($path);
            self::assertArrayHasKey('runtime', $request, $file);
        }

        sort($runtime);
        sort($documented);
        sort($examples);
        self::assertSame($runtime, $documented);
        self::assertSame($runtime, $examples);
    }

    /** @return array<string, array{string, string}> */
    public static function realms(): array
    {
        return ['user' => ['UserApi', 'user-api'], 'admin' => ['SystemAdminApi', 'system-admin-api']];
    }

    private static function normalize(string $path): string
    {
        return preg_replace('/\{\{?[^}]+\}\}?/', '{}', $path) ?? $path;
    }
}
