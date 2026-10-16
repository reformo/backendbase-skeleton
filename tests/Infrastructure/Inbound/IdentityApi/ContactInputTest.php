<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Adapters\Http\Identity\ContactHttpInput;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class ContactInputTest extends TestCase
{
    #[DataProvider('invalidRequests')]
    public function testInvalidContactInputCannotCreateEvidence(string $field, mixed $invalid): void
    {
        $fixture         = IdentityFixture::create();
        $api             = IdentityApiFixture::create($fixture, Realm::USER);
        $payload         = ['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => ContactScenario::token()];
        $payload[$field] = $invalid;
        self::assertSame(400, $api->request('POST', '/auth/contact-verifications', $payload)->getStatusCode());
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM contact_info'));
    }

    public function testInvalidCodesAndMissingJsonCannotConfirmEvidence(): void
    {
        $fixture = IdentityFixture::create();
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $path    = '/auth/contact-verifications/' . IdentityScenario::id() . '/confirm';
        self::assertSame(400, $api->request('POST', $path, ['verificationContextToken' => ContactScenario::token(), 'code' => '12345'])->getStatusCode());
        self::assertSame(400, $api->request('POST', '/auth/contact-verifications')->getStatusCode());
        $request = new ServerRequestFactory()->createServerRequest('POST', '/auth/contact-verifications', ['REMOTE_ADDR' => 'invalid']);
        $request = $request->withHeader('Content-Type', 'application/json')->withParsedBody(['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => ContactScenario::token()]);
        self::assertSame(400, $api->handle($request)->getStatusCode());
    }

    #[DataProvider('policies')]
    public function testUntrustedRoutePoliciesAreRejected(bool $authenticated): void
    {
        $request = new ServerRequestFactory()->createServerRequest('POST', '/');
        if ($authenticated) {
            $request = $request->withAttribute('contactAction', 'request')->withAttribute('expectedRealm', Realm::ADMIN)->withAttribute('contactAuthenticated', true)->withHeader('Content-Type', 'application/json');
        }

        $this->expectException(AuthenticationDenied::class);
        new ContactHttpInput()->read($request, []);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidRequests(): iterable
    {
        yield 'missing token' => ['verificationContextToken', null];
        yield 'padded token' => ['verificationContextToken', str_repeat('A', 43) . '='];
        yield 'noncanonical token' => ['verificationContextToken', str_repeat('A', 42) . 'B'];
        yield 'unsupported contact' => ['contactType', 'fax'];
        yield 'invalid email' => ['contactValue', 'invalid'];
    }

    /** @return iterable<string, array{bool}> */
    public static function policies(): iterable
    {
        yield 'missing trusted policy' => [false];
        yield 'missing trusted principal' => [true];
    }
}
