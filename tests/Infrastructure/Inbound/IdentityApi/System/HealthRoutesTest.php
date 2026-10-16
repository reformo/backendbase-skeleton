<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\System;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Shared\Health\ReadinessCheck;
use Backendbase\Shared\Health\ReadinessChecks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class HealthRoutesTest extends TestCase
{
    #[DataProvider('realms')]
    public function testAnonymousLivenessDoesNotRunDependencyChecks(Realm $realm): void
    {
        $check       = $this->createMock(ReadinessCheck::class);
        $expectation = $check->expects(self::never());
        $expectation->method('check');
        $fixture  = IdentityFixture::create([ReadinessChecks::class => new ReadinessChecks([$check])]);
        $api      = IdentityApiFixture::create($fixture, $realm);
        $response = $api->request('GET', '/_status');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'alive'], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[DataProvider('realms')]
    public function testAnonymousReadinessReportsSuccessfulDependencyCheck(Realm $realm): void
    {
        $check = $this->createMock(ReadinessCheck::class);
        $name  = $check->method('name');
        $name->willReturn('redis');
        $expectation = $check->expects(self::once());
        $expectation->method('check');
        $fixture  = IdentityFixture::create([ReadinessChecks::class => new ReadinessChecks([$check])]);
        $api      = IdentityApiFixture::create($fixture, $realm);
        $response = $api->request('GET', '/_status/ready');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ready', 'checks' => ['redis' => 'ready']], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[DataProvider('realms')]
    public function testAnonymousReadinessHidesDependencyFailureDetails(Realm $realm): void
    {
        $check = $this->createMock(ReadinessCheck::class);
        $name  = $check->method('name');
        $name->willReturn('redis');
        $expectation = $check->expects(self::once());
        $method      = $expectation->method('check');
        $method->willThrowException(new RuntimeException('Sensitive connection details.'));
        $fixture  = IdentityFixture::create([ReadinessChecks::class => new ReadinessChecks([$check])]);
        $api      = IdentityApiFixture::create($fixture, $realm);
        $response = $api->request('GET', '/_status/ready');
        self::assertSame(503, $response->getStatusCode());
        self::assertSame(['status' => 'unavailable', 'checks' => ['redis' => 'unavailable']], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return array<string, array{Realm}> */
    public static function realms(): array
    {
        return ['user' => [Realm::USER], 'admin' => [Realm::ADMIN]];
    }
}
