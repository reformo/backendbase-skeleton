<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Registration;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Registration\RegistrationPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Configuration\Idempotency\ReplaySettings;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\Infrastructure\Composition\ProductionContainerFixture;
use Tests\Infrastructure\Inbound\IdentityApi\Contacts\UserEmailVerificationTest;
use Tests\Infrastructure\Inbound\IdentityApi\ContactVerificationFlowTest;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class RegistrationCompatibilityTest extends TestCase
{
    #[DataProvider('legacyAccounts')]
    public function testLegacyAccountAndSessionDecisionsRemainUnchanged(Realm $realm, bool $owned): void
    {
        $fixture = RegistrationPolicyTest::fixture(3);
        IdentityScenario::admin($fixture, '+905551234567', 'ada@example.com');
        $api        = IdentityApiFixture::create($fixture, $realm);
        $token      = UserEmailVerificationTest::login($api);
        $connection = $fixture->connection();
        $connection->executeStatement("UPDATE account SET state = json_set(json_remove(state, '$.registrationAccepted'), '$.ownershipVerified', json(?))", [$owned ? 'true' : 'false']);
        $before   = $connection->fetchOne('SELECT state FROM account');
        $response = $api->request('GET', '/identity', token: $token);
        self::assertSame($owned ? 200 : 401, $response->getStatusCode());
        $login = $api->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'valid-password-123']);
        self::assertSame($owned ? 200 : 401, $login->getStatusCode());
        self::assertSame($before, $connection->fetchOne('SELECT state FROM account'));
    }

    public function testStricterConfigurationDoesNotChangeAcceptedAccountsOrExistingSessions(): void
    {
        $fixture = RegistrationPolicyTest::fixture(0);
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $payload = ['email' => 'ada@example.com', 'mobile' => '+905551234567', 'password' => 'valid-password-123'];
        self::assertSame(201, UserEmailVerificationTest::responseStatus($api->request('POST', '/auth/register', $payload)));
        $token      = UserEmailVerificationTest::login($api);
        $connection = $fixture->connection();
        $before     = $connection->fetchOne('SELECT state FROM account');
        $changed    = self::withPolicy($fixture, new RegistrationPolicy(true, true));
        $newApi     = IdentityApiFixture::create($changed, Realm::USER);
        self::assertSame(200, UserEmailVerificationTest::responseStatus($newApi->request('GET', '/identity', token: $token)));
        UserEmailVerificationTest::login($newApi);
        $payload['email']  = 'another@example.com';
        $payload['mobile'] = '+905551234568';
        self::assertSame(400, UserEmailVerificationTest::responseStatus($newApi->request('POST', '/auth/register', $payload)));
        self::assertSame($before, $connection->fetchOne('SELECT state FROM account'));
    }

    public function testRegistrationAcceptanceDoesNotAuthorizeAdminAuthentication(): void
    {
        $fixture = RegistrationPolicyTest::fixture(0);
        IdentityScenario::admin($fixture, '+905551234567', 'ada@example.com');
        $connection = $fixture->connection();
        $connection->executeStatement("UPDATE account SET state = json_set(state, '$.ownershipVerified', json('false'))");
        $userApi = IdentityApiFixture::create($fixture, Realm::USER);
        UserEmailVerificationTest::login($userApi);
        $adminApi = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $response = $adminApi->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'valid-password-123']);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testExplicitRegistrationRejectionIsNotReplacedByOwnershipEvidence(): void
    {
        $fixture = RegistrationPolicyTest::fixture(1);
        IdentityScenario::registered($fixture);
        $connection = $fixture->connection();
        $connection->executeStatement("UPDATE account SET state = json_set(state, '$.registrationAccepted', json('false'))");
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $response = $api->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'valid-password-123']);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testAccountWithoutRegistrationProofCanVerifyEmailAfterLoginWithoutChangingAcceptance(): void
    {
        $fixture = RegistrationPolicyTest::fixture(0);
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $api->request('POST', '/auth/register', ['email' => 'ada@example.com', 'mobile' => '+905551234567', 'password' => 'valid-password-123']);
        $token      = UserEmailVerificationTest::login($api);
        $connection = $fixture->connection();
        $before     = $connection->fetchOne('SELECT state FROM account');
        $context    = ContactScenario::token();
        $response   = $api->request('POST', '/identity/contact-verifications', ['contactType' => 'email', 'verificationContextToken' => $context], $token);
        self::assertSame(200, $response->getStatusCode());
        $contact = UserFlowTest::resource($response, 'contactInfo');
        $id      = UserFlowTest::text($contact, 'contactInfoId');
        $proof   = ['verificationContextToken' => $context, 'code' => ContactVerificationFlowTest::code($fixture)];
        self::assertSame(200, UserEmailVerificationTest::responseStatus($api->request('POST', '/identity/contact-verifications/' . $id . '/confirm', $proof, $token)));
        self::assertSame($before, $connection->fetchOne('SELECT state FROM account'));
        self::assertSame(200, UserEmailVerificationTest::responseStatus($api->request('GET', '/identity', token: $token)));
        self::assertSame(1, $connection->fetchOne("SELECT COUNT(*) FROM contact_verification WHERE purpose = 'user-contact-verification' AND verified_at IS NOT NULL"));
    }

    /** @return iterable<string, array{Realm, bool}> */
    public static function legacyAccounts(): iterable
    {
        yield 'verified User' => [Realm::USER, true];
        yield 'unverified User' => [Realm::USER, false];
        yield 'verified Admin' => [Realm::ADMIN, true];
        yield 'unverified Admin' => [Realm::ADMIN, false];
    }

    private static function withPolicy(IdentityFixture $fixture, RegistrationPolicy $policy): IdentityFixture
    {
        $old       = $fixture->container();
        $overrides = [RegistrationPolicy::class => $policy];
        foreach ([HttpHeaderSettings::class, Connection::class, Configuration::class, Clock::class, IdentitySettings::class, ReplaySettings::class, ContactNotifier::class, VerificationLimiter::class, TokenRegistry::class, LoggerInterface::class] as $class) {
            $overrides[$class] = $old->get($class);
        }

        return new IdentityFixture($fixture->connection(), ProductionContainerFixture::build($overrides));
    }
}
