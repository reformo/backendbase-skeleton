<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Adapters\Http\Bootstrap\ConsumerApiTarget;
use PHPUnit\Framework\TestCase;

final class PreflightTest extends TestCase
{
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
