<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactConfirmation;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;

use function range;

final class ContactQuotaTest extends TestCase
{
    public function testNewContextsCannotBypassTheHourlyContactSendLimit(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $requests  = $container->get(ContactRequests::class);
        $value     = new ContactValue('email', 'ada@example.com');
        foreach (range(1, 5) as $attempt) {
            $requests->request($value, new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), new VerificationClient('127.0.0.1'));
            IdentityScenario::advance($fixture, 'PT60S');
        }

        $this->expectException(VerificationLimited::class);
        $requests->request($value, new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), new VerificationClient('127.0.0.2'));
    }

    public function testChangingContactValuesCannotBypassTheCallerIpLimit(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $requests  = $container->get(ContactRequests::class);
        $client    = new VerificationClient('127.0.0.1');
        foreach (range(1, 20) as $index) {
            $requests->request(new ContactValue('email', 'user' . $index . '@example.com'), new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $client);
        }

        $this->expectException(VerificationLimited::class);
        $requests->request(new ContactValue('email', 'other@example.com'), new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $client);
    }

    public function testFailedConfirmationBudgetSurvivesANewContext(): void
    {
        $fixture      = IdentityFixture::create();
        $container    = $fixture->container();
        $requests     = $container->get(ContactRequests::class);
        $confirmation = $container->get(ContactConfirmation::class);
        $value        = new ContactValue('mobile', '+905551234567');
        $client       = new VerificationClient('127.0.0.1');
        foreach (range(1, 2) as $cycle) {
            $token            = new VerificationContextToken(ContactScenario::token());
            [$contact, $code] = $requests->request($value, $token, new VerificationScope(Realm::USER), $client);
            self::assertNotNull($code);
            $wrong = $code->forDelivery() === '000000' ? new VerificationCode('111111') : new VerificationCode('000000');
            self::failAttempts($confirmation, $contact->id(), $token, $wrong);
            IdentityScenario::advance($fixture, 'PT60S');
        }

        $token            = new VerificationContextToken(ContactScenario::token());
        [$contact, $code] = $requests->request($value, $token, new VerificationScope(Realm::USER), $client);
        self::assertNotNull($code);
        $this->expectException(VerificationLimited::class);
        $confirmation->confirm($contact->id(), $token, new VerificationScope(Realm::USER), $code);
    }

    private static function failAttempts(ContactConfirmation $confirmation, string $id, VerificationContextToken $token, VerificationCode $wrong): void
    {
        foreach (range(1, 5) as $attempt) {
            try {
                $confirmation->confirm($id, $token, new VerificationScope(Realm::USER), $wrong);
                self::fail('Invalid codes must be rejected.');
            } catch (AuthenticationDenied $exception) {
                self::assertSame('Contact verification is not permitted.', $exception->getMessage());
            }
        }
    }
}
