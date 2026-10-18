<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Registration;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Configuration\Identity\RegistrationSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\Contacts\UserEmailVerificationTest;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class RegistrationPolicyTest extends TestCase
{
    #[DataProvider('cases')]
    public function testRegistrationAndLoginFollowTheConfiguredRequirements(bool $example, int $requirements, int $proofs): void
    {
        $fixture    = self::fixture($requirements);
        $values     = ContactScenario::verified($fixture);
        $connection = $fixture->connection();
        if (($proofs & 1) === 0) {
            $connection->executeStatement("DELETE FROM contact_verification WHERE contact_type = 'mobile'");
        }

        if (($proofs & 2) === 0) {
            $connection->executeStatement("DELETE FROM contact_verification WHERE contact_type = 'email'");
        }

        $api      = $example ? IdentityApiFixture::example($fixture) : IdentityApiFixture::create($fixture, Realm::USER);
        $accepted = ($requirements & $proofs) === $requirements;
        $response = $api->request('POST', '/auth/register', $values);
        self::assertSame($accepted ? 201 : 401, $response->getStatusCode());
        self::assertSame($accepted ? 1 : 0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
        self::assertSame($accepted ? 1 : 0, $connection->fetchOne('SELECT COUNT(*) FROM user'));
        $consumed = $connection->fetchOne('SELECT COUNT(*) FROM contact_verification WHERE account_id IS NOT NULL');
        self::assertSame($accepted ? ($proofs & 1) + (($proofs & 2) >> 1) : 0, $consumed);
        if (! $accepted) {
            return;
        }

        $stored = $connection->fetchOne('SELECT state FROM account');
        self::assertIsString($stored);
        $state = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        self::assertTrue($state['registrationAccepted']);
        self::assertSame($proofs !== 0, $state['ownershipVerified']);
        self::assertSame(1, $state['authVersion']);
        $token = UserEmailVerificationTest::login($api);
        self::assertSame(200, UserEmailVerificationTest::responseStatus($api->request('GET', '/identity', token: $token)));
    }

    #[DataProvider('requirements')]
    public function testContextCanBeOmittedOnlyWhenNeitherProofIsRequired(int $requirements): void
    {
        $fixture  = self::fixture($requirements);
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $response = $api->request('POST', '/auth/register', ['email' => 'ada@example.com', 'mobile' => '+905551234567', 'password' => 'valid-password-123']);
        self::assertSame($requirements === 0 ? 201 : 400, $response->getStatusCode());
        $connection = $fixture->connection();
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM contact_verification'));
    }

    public function testSuppliedMalformedContextIsRejectedEvenWithoutVerificationRequirements(): void
    {
        $fixture  = self::fixture(0);
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $response = $api->request('POST', '/auth/register', ['email' => 'ada@example.com', 'mobile' => '+905551234567', 'password' => 'valid-password-123', 'verificationContextToken' => 'invalid']);
        self::assertSame(400, $response->getStatusCode());
        $connection = $fixture->connection();
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
    }

    public static function fixture(int $requirements): IdentityFixture
    {
        $settings = new RegistrationSettings(new Settings([
            'identity' => [
                'registration' => [
                    'require-mobile-verification' => ($requirements & 1) !== 0,
                    'require-email-verification' => ($requirements & 2) !== 0,
                ],
            ],
        ]));

        return ExampleApiFixture::identity([RegistrationSettings::class => $settings]);
    }

    /** @return iterable<string, array{bool, int, int}> */
    public static function cases(): iterable
    {
        foreach ([false, true] as $example) {
            yield from self::apiCases($example);
        }
    }

    /** @return iterable<string, array{bool, int, int}> */
    private static function apiCases(bool $example): iterable
    {
        foreach ([0, 1, 2, 3] as $requirements) {
            yield from self::proofCases($example, $requirements);
        }
    }

    /** @return iterable<string, array{bool, int, int}> */
    private static function proofCases(bool $example, int $requirements): iterable
    {
        foreach ([0, 1, 2, 3] as $proofs) {
            yield ($example ? 'ExampleApi' : 'UserApi') . ' required ' . $requirements . ' proofs ' . $proofs => [$example, $requirements, $proofs];
        }
    }

    /** @return iterable<array{int}> */
    public static function requirements(): iterable
    {
        foreach ([0, 1, 2, 3] as $requirements) {
            yield [$requirements];
        }
    }
}
