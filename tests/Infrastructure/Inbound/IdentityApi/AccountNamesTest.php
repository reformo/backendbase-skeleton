<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationClock;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class AccountNamesTest extends TestCase
{
    public function testRegistrationPersistsOptionalAccountNamesAndReturnsThemInCurrentIdentity(): void
    {
        $fixture = IdentityFixture::create();
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $values  = ContactScenario::verified($fixture);
        self::assertSame(201, $api->request('POST', '/auth/register', [...$values, 'firstName' => 'Mehmet', 'lastName' => 'Korkmaz'])->getStatusCode());
        $principal = IdentityScenario::principal($fixture);
        $container = $fixture->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        $resource  = UserFlowTest::resource($api->request('GET', '/identity', token: $token), 'user');
        self::assertSame('Mehmet', $resource['firstName']);
        self::assertSame('Korkmaz', $resource['lastName']);
        self::assertSame(['first_name' => 'Mehmet', 'last_name' => 'Korkmaz'], $fixture->connection()->fetchAssociative('SELECT first_name, last_name FROM identity_account'));
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNamesAreRejectedBeforeCreatingAnAccount(string $firstName): void
    {
        $fixture = IdentityFixture::create();
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $values  = ContactScenario::verified($fixture);
        self::assertSame(400, $api->request('POST', '/auth/register', [...$values, 'firstName' => $firstName])->getStatusCode());
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_account'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'surrounding whitespace' => [' Mehmet'];
        yield 'oversized' => [str_repeat('M', 161)];
        yield 'control characters' => ["Mehmet\nKorkmaz"];
    }
}
