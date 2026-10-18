<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactConfirmation;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactDelivery;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use RuntimeException;

use function base64_encode;
use function random_bytes;
use function rtrim;
use function strtr;

final class ContactScenario
{
    public static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** @return array{email: string, mobile: string, password: string, verificationContextToken: string} */
    public static function verified(IdentityFixture $fixture, string $email = 'ada@example.com', string $mobile = '+905551234567'): array
    {
        IdentityScenario::advance($fixture, 'PT61S');
        $token = self::token();
        self::verify($fixture, new ContactValue('email', $email), $token);
        self::verify($fixture, new ContactValue('mobile', $mobile), $token);

        return ['email' => $email, 'mobile' => $mobile, 'password' => 'valid-password-123', 'verificationContextToken' => $token];
    }

    public static function input(IdentityFixture $fixture, string $email = 'ada@example.com', string $mobile = '+905551234567'): RegistrationInput
    {
        $values = self::verified($fixture, $email, $mobile);

        return new RegistrationInput(...$values);
    }

    private static function verify(IdentityFixture $fixture, ContactValue $value, string $tokenValue): void
    {
        $container        = $fixture->container();
        $requests         = $container->get(ContactRequests::class);
        $confirmation     = $container->get(ContactConfirmation::class);
        $delivery         = $container->get(ContactDelivery::class);
        $token            = new VerificationContextToken($tokenValue);
        $scope            = new VerificationScope(Realm::USER);
        [$contact, $code] = $requests->request($value, $token, $scope, new VerificationClient('127.0.0.1'));
        $delivery->send($contact, $code);
        if (! $code instanceof VerificationCode) {
            throw new RuntimeException('A new fixture context must issue a code.');
        }

        $confirmation->confirm($contact->id(), $token, $scope, $code);
    }
}
