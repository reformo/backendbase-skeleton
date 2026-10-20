<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

use function explode;

final class SessionHttpScenario
{
    /** @return array{IdentityFixture, IdentityApiFixture, array<string, mixed>} */
    public static function login(Realm $realm = Realm::USER, bool $example = false): array
    {
        $fixture    = $example ? ExampleApiFixture::identity() : IdentityFixture::create();
        $identifier = 'ada@example.com';
        if ($realm === Realm::ADMIN) {
            IdentityScenario::admin($fixture);
            $identifier = 'admin@example.com';
        }

        if ($realm === Realm::USER) {
            IdentityScenario::verified($fixture);
        }

        $api      = $example ? IdentityApiFixture::example($fixture) : IdentityApiFixture::create($fixture, $realm);
        $response = $api->request('POST', '/auth/login', ['identifier' => $identifier, 'password' => 'valid-password-123']);
        Assert::assertSame(200, $response->getStatusCode());

        return [$fixture, $api, UserFlowTest::resource($response, 'session')];
    }

    /** @return array<string, string> */
    public static function cookie(ResponseInterface $response): array
    {
        $header = $response->getHeaderLine('Set-Cookie');
        Assert::assertStringContainsString('; Secure; HttpOnly; SameSite=Strict', $header);
        Assert::assertStringNotContainsString('Domain=', $header);
        [$pair]         = explode(';', $header);
        [$name, $value] = explode('=', $pair, 2);

        return [$name => $value];
    }
}
