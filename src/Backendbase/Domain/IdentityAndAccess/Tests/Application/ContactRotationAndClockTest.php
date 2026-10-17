<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactConfirmation;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ObservedContactStore;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\RotatedContactFixture;
use DI\Container;
use PHPUnit\Framework\TestCase;

final class ContactRotationAndClockTest extends TestCase
{
    public function testKeyRotationPreservesContextUniquenessConfirmationAndQuotas(): void
    {
        $fixture             = IdentityFixture::create();
        $contextValue        = ContactScenario::token();
        $token               = new VerificationContextToken($contextValue);
        $scope               = new VerificationScope(Realm::USER);
        $client              = new VerificationClient('127.0.0.1');
        $container           = $fixture->container();
        $requests            = $container->get(ContactRequests::class);
        [$email, $emailCode] = $requests->request(new ContactValue('email', 'ada@example.com'), $token, $scope, $client);
        self::assertNotNull($emailCode);
        $rotated              = RotatedContactFixture::create($fixture);
        $container            = $rotated->container();
        $requests             = $container->get(ContactRequests::class);
        [$sameEmail, $noCode] = $requests->request(new ContactValue('email', 'ada@example.com'), $token, $scope, $client);
        self::assertSame($email->id(), $sameEmail->id());
        self::assertNull($noCode);
        [$mobile, $mobileCode] = $requests->request(new ContactValue('mobile', '+905551234567'), $token, $scope, $client);
        self::assertNotNull($mobileCode);
        $confirmation = $container->get(ContactConfirmation::class);
        $confirmed    = $confirmation->confirm($email->id(), $token, $scope, $emailCode);
        $confirmation->confirm($mobile->id(), $token, $scope, $mobileCode);
        IdentityScenario::advance($rotated, 'PT6M');
        $retry = $confirmation->confirm($email->id(), $token, $scope, $emailCode);
        self::assertSame($confirmed->snapshot()['verifiedAt'], $retry->snapshot()['verifiedAt']);
        $registration = $container->get(RegistrationWorkflow::class);
        $result       = $registration->register(IdentityScenario::id(), new RegistrationInput('ada@example.com', '+905551234567', 'valid-password-123', $email->id(), $mobile->id(), $contextValue));
        self::assertNotEmpty($result['accountId']);
        self::assertSame(2, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM contact_verification'));
    }

    public function testExpiredEvidenceIsRejectedWhenTheClockAdvancesDuringLockAcquisition(): void
    {
        $fixture   = IdentityFixture::create();
        $values    = ContactScenario::verified($fixture);
        $container = $fixture->container();
        self::assertInstanceOf(Container::class, $container);
        $store = $container->get(ContactStore::class);
        $reads = 0;
        $container->set(ContactStore::class, new ObservedContactStore($store, static function () use ($fixture, &$reads): void {
            $reads++;
            if ($reads !== 2) {
                return;
            }

            IdentityScenario::advance($fixture, 'PT12H');
        }));
        $workflow = $container->get(RegistrationWorkflow::class);
        try {
            $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
            self::fail('Evidence must be checked after both locks.');
        } catch (ContactVerificationExpired) {
            self::assertSame(2, $reads);
            self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM account'));
        }
    }

    public function testRotatingProofKeysDoesNotResetTheContactSendCooldown(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $requests  = $container->get(ContactRequests::class);
        $value     = new ContactValue('email', 'ada@example.com');
        $client    = new VerificationClient('127.0.0.1');
        $requests->request($value, new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $client);
        $rotated   = RotatedContactFixture::create($fixture);
        $container = $rotated->container();
        $requests  = $container->get(ContactRequests::class);
        $this->expectException(VerificationLimited::class);
        $requests->request($value, new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $client);
    }
}
