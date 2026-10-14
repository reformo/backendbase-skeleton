<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Inbound\IdentityApi\IdentityRoutes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Symfony\Component\Yaml\Yaml;

use function glob;
use function preg_replace;
use function sort;
use function str_replace;
use function str_starts_with;
use function strtoupper;

final class ApiContractTest extends TestCase
{
    #[DataProvider('realms')]
    public function testRoutesOpenApiAndBrunoHaveTheSamePublicOperations(Realm $realm, string $slug): void
    {
        $app = AppFactory::create();
        IdentityRoutes::register($app, $realm);
        $collector = $app->getRouteCollector();
        $runtime   = [];
        foreach ($collector->getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                if ($method === 'OPTIONS') {
                    continue;
                }

                $runtime[] = $method . ' ' . self::normalize($route->getPattern());
            }
        }

        $specification = Yaml::parseFile('resources/api-docs/' . $slug . '/' . $slug . '-openapi.yml');
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

    /** @return array<string, array{Realm, string}> */
    public static function realms(): array
    {
        return ['user' => [Realm::USER, 'user-api'], 'admin' => [Realm::ADMIN, 'system-admin-api']];
    }

    private static function normalize(string $path): string
    {
        return preg_replace('/\{\{?[^}]+\}\}?/', '{}', $path) ?? $path;
    }
}
