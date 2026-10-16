<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Adapters\Http\Bootstrap\ConsumerApiTarget;
use cebe\openapi\spec\Paths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Http\ApiResponseAssertions;

final class PreflightTest extends TestCase
{
    #[DataProvider('apis')]
    public function testEveryDocumentedOperationAllowsAnonymousPreflight(Realm $realm, string $slug): void
    {
        $api      = IdentityApiFixture::create(IdentityFixture::create(), $realm);
        $document = ApiResponseAssertions::document('resources/api-docs/' . $slug . '/' . $slug . '-openapi.yml');
        $paths    = $document->paths;
        self::assertInstanceOf(Paths::class, $paths);
        foreach ($paths->getPaths() as $path => $pathItem) {
            $response = $api->request('OPTIONS', $path);
            self::assertSame(204, $response->getStatusCode(), $path);
            self::assertSame('', (string) $response->getBody(), $path);
        }
    }

    /** @return iterable<string, array{Realm, string}> */
    public static function apis(): iterable
    {
        yield 'UserApi' => [Realm::USER, 'user-api'];
        yield 'SystemAdminApi' => [Realm::ADMIN, 'system-admin-api'];
    }

    public function testBrowserPreflightUsesOnlyAWhitelistedApiPathAndRequiresNoSession(): void
    {
        $target = ConsumerApiTarget::fromRequest(['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/user-api/auth/login']);
        self::assertNotNull($target);
        self::assertSame('UserApi', $target->name());
        self::assertNull(ConsumerApiTarget::fromRequest(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/user-api/auth/login']));
        self::assertNull(ConsumerApiTarget::fromRequest(['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/untrusted-api/auth/login']));
        self::assertNull(ConsumerApiTarget::fromRequest(['HTTP_X_SOURCE_ID' => ['user']]));
        self::assertSame('SystemAdminApi', ConsumerApiTarget::fromRequest(['HTTP_X_SOURCE_ID' => 'system-admin'])?->name());
        $api = IdentityApiFixture::create(IdentityFixture::create(), Realm::USER);
        self::assertSame(204, $api->request('OPTIONS', '/auth/login')->getStatusCode());
    }
}
