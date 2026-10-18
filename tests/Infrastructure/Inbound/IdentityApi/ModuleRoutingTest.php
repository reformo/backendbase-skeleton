<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\ModuleRoutes as SystemAdminModuleRoutes;
use Backendbase\Infrastructure\Inbound\UserApi\Controllers\ModuleRoutes as UserModuleRoutes;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorInterface;
use Tests\Support\Http\ApiResponseAssertions;

use function array_values;
use function sort;
use function strtoupper;

final class ModuleRoutingTest extends TestCase
{
    /** @param class-string<UserModuleRoutes|SystemAdminModuleRoutes> $registryClass */
    #[DataProvider('apis')]
    public function testModuleRegistryAndHandlersMatchOpenApiTags(string $apiName, string $slug, string $registryClass): void
    {
        $namespace = 'Backendbase\\Infrastructure\\Inbound\\' . $apiName . '\\Controllers\\';
        $document  = ApiResponseAssertions::document('resources/api-docs/' . $slug . '/' . $slug . '-openapi.yml');
        $tags      = $document->tags;
        $expected  = [];
        foreach ($tags as $tag) {
            $expected[] = $namespace . $tag->name . '\\ModuleConfig';
        }

        $registry = new $registryClass();
        $modules  = $registry->getModules();
        $actual   = array_values($modules);
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
        foreach ($modules as $routeKey => $moduleClass) {
            $module = new $moduleClass();
            self::assertSame($routeKey, $module->routeKey());
        }

        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $app       = AppFactory::create(container: $container);
        $register  = require 'src/Backendbase/Infrastructure/Inbound/' . $apiName . '/routes.php';
        $register($app);
        $collector = $app->getRouteCollector();
        $paths     = $document->paths;
        self::assertInstanceOf(Paths::class, $paths);
        foreach ($paths->getPaths() as $path => $pathItem) {
            self::assertPathRoutes($path, $pathItem, $collector, $namespace);
        }
    }

    #[DataProvider('protectedRoutes')]
    public function testProtectedRouteRequiresATokenFromItsOwnRealm(Realm $realm, string $method, string $path): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $otherRealm = $realm === Realm::USER ? Realm::ADMIN : Realm::USER;
        $token      = AdminContactVerificationTest::token($fixture, $otherRealm);
        $api        = IdentityApiFixture::create($fixture, $realm);

        $response = $api->request($method, $path);
        self::assertSame(401, $response->getStatusCode());
        $response = $api->request($method, $path, token: $token);
        self::assertSame(401, $response->getStatusCode());
    }

    /** @return iterable<string, array{Realm, string, string}> */
    public static function protectedRoutes(): iterable
    {
        yield 'user contact request' => [Realm::USER, 'POST', '/identity/contact-verifications'];
        yield 'user contact confirm' => [Realm::USER, 'POST', '/identity/contact-verifications/' . IdentityScenario::id() . '/confirm'];
        yield 'user contact resend' => [Realm::USER, 'POST', '/identity/contact-verifications/' . IdentityScenario::id() . '/resend'];
        yield 'user identity' => [Realm::USER, 'GET', '/identity'];
        yield 'user revoke token' => [Realm::USER, 'DELETE', '/identity/token'];
        yield 'user end session' => [Realm::USER, 'DELETE', '/identity/session'];
        yield 'user close account' => [Realm::USER, 'DELETE', '/identity/account'];
        yield 'admin identity' => [Realm::ADMIN, 'GET', '/identity'];
        yield 'admin revoke token' => [Realm::ADMIN, 'DELETE', '/identity/token'];
        yield 'admin end session' => [Realm::ADMIN, 'DELETE', '/identity/session'];
        yield 'admin close account' => [Realm::ADMIN, 'DELETE', '/identity/account'];
        yield 'admin contact request' => [Realm::ADMIN, 'POST', '/identity/contact-verifications'];
        yield 'admin contact confirm' => [Realm::ADMIN, 'POST', '/identity/contact-verifications/0199edbf-4e00-7000-8000-000000000001/confirm'];
        yield 'admin contact resend' => [Realm::ADMIN, 'POST', '/identity/contact-verifications/0199edbf-4e00-7000-8000-000000000001/resend'];
        yield 'admin invite' => [Realm::ADMIN, 'POST', '/administration/admins/invite'];
        yield 'admin approve' => [Realm::ADMIN, 'POST', '/administration/admins/approve'];
        yield 'admin change actor' => [Realm::ADMIN, 'POST', '/administration/actors/change'];
        yield 'admin actor history' => [Realm::ADMIN, 'GET', '/administration/actors/USER/0199edbf-4e00-7000-8000-000000000001'];
    }

    private static function assertPathRoutes(string $path, PathItem $pathItem, RouteCollectorInterface $collector, string $namespace): void
    {
        foreach ($pathItem->getOperations() as $method => $operation) {
            $operationId = $operation->operationId;
            $route       = $collector->getNamedRoute($operationId);
            self::assertSame($path, $route->getPattern());
            self::assertSame([strtoupper($method)], $route->getMethods());
            $tags = $operation->tags;
            self::assertCount(1, $tags);
            $controller = $route->getCallable();
            self::assertIsString($controller);
            self::assertStringStartsWith($namespace . $tags[0] . '\\Handlers\\', $controller);
        }
    }

    /** @return iterable<string, array{string, string, class-string<UserModuleRoutes|SystemAdminModuleRoutes>}> */
    public static function apis(): iterable
    {
        yield 'UserApi' => ['UserApi', 'user-api', UserModuleRoutes::class];
        yield 'SystemAdminApi' => ['SystemAdminApi', 'system-admin-api', SystemAdminModuleRoutes::class];
    }
}
