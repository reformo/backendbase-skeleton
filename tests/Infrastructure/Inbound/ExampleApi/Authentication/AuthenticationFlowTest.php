<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class AuthenticationFlowTest extends TestCase
{
    #[DataProvider('revocations')]
    public function testExampleAndUserApisShareRegistrationLoginAndRevocation(string $resource, bool $revokeInExample): void
    {
        $identity = ExampleApiFixture::identity();
        $example  = IdentityApiFixture::example($identity);
        $user     = IdentityApiFixture::create($identity, Realm::USER);
        $response = $example->request('POST', '/auth/register', [...ContactScenario::verified($identity), 'firstName' => 'Ada', 'lastName' => 'Lovelace']);
        self::assertSame(201, $response->getStatusCode());
        $registered = UserFlowTest::resource($response, 'registration');
        $login      = ['identifier' => 'ada@example.com', 'password' => 'valid-password-123'];
        $response   = $example->request('POST', '/auth/login', $login);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $session = UserFlowTest::resource($response, 'session');
        $token   = UserFlowTest::text($session, 'accessToken');
        $current = $example->request('GET', '/identity', token: $token);
        self::assertSame(200, $current->getStatusCode());
        $account = UserFlowTest::resource($current, 'user');
        self::assertSame($registered['accountId'], $account['accountId']);
        self::assertSame('USER', $account['realm']);
        self::assertSame('Ada', $account['firstName']);
        self::assertSame('Lovelace', $account['lastName']);
        self::assertSame(200, $user->request('GET', '/identity', token: $token)->getStatusCode());

        $userLogin = $user->request('POST', '/auth/login', $login);
        $session   = UserFlowTest::resource($userLogin, 'session');
        $userToken = UserFlowTest::text($session, 'accessToken');
        self::assertSame(200, $example->request('GET', '/identity', token: $userToken)->getStatusCode());
        $source = $revokeInExample ? $example : $user;
        $result = $source->request('DELETE', '/identity/' . $resource, token: $token);
        self::assertSame(200, $result->getStatusCode());
        self::assertSame(['completed' => true], UserFlowTest::resource($result, $resource));
        self::assertSame(401, $example->request('GET', '/identity', token: $token)->getStatusCode());
        self::assertSame(401, $user->request('GET', '/identity', token: $token)->getStatusCode());
        $otherStatus = $resource === 'account' ? 401 : 200;
        self::assertSame($otherStatus, $example->request('GET', '/identity', token: $userToken)->getStatusCode());
        self::assertSame($otherStatus, $example->request('POST', '/auth/login', $login)->getStatusCode());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function revocations(): iterable
    {
        yield 'Example token' => ['token', true];
        yield 'Example session' => ['session', true];
        yield 'Example account' => ['account', true];
        yield 'User token' => ['token', false];
        yield 'User session' => ['session', false];
        yield 'User account' => ['account', false];
    }
}
