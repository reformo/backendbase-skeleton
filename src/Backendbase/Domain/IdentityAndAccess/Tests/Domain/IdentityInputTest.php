<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginIdentifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Shared\Exception\InvalidUserInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class IdentityInputTest extends TestCase
{
    public function testAsciiBusinessKeysUseStableLowercaseNormalization(): void
    {
        $login   = new LoginIdentifier('INFO.User@EXAMPLE.COM');
        $email   = new AccountEmail('INFO.User@EXAMPLE.COM');
        $contact = $login->contact();
        self::assertSame('info.user@example.com', $contact->toString());
        self::assertSame('info.user@example.com', $email->toString());
        $password = new Password(str_repeat('ş', 128));
        self::assertSame(str_repeat('ş', 128), $password->forHashing());
    }

    #[DataProvider('invalidLogins')]
    public function testLoginIdentifiersRejectInputWithoutSilentSanitization(string $value): void
    {
        $this->expectException(InvalidUserInput::class);
        new LoginIdentifier($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLogins(): iterable
    {
        yield 'empty' => [''];
        yield 'too few digits' => ['+1'];
        yield 'too many digits' => ['+1234567890123456'];
        yield 'zero country prefix' => ['+0123'];
        yield 'formatted mobile' => ['+90 555 123 45 67'];
        yield 'extension' => ['+905551234567x12'];
        yield 'Unicode digits' => ['+９０５５５１２３４５６７'];
        yield 'mobile newline' => ["+905551234567\n"];
        yield 'too short' => ['ab'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'leading separator' => ['.abc'];
        yield 'whitespace' => [' abc'];
        yield 'Turkish uppercase I' => ['İsim'];
        yield 'Turkish lowercase I' => ['ısım'];
        yield 'markup' => ['<abc>'];
        yield 'control character' => ["abc\n"];
    }

    public function testMobileIdentifiersPreserveInternationalBounds(): void
    {
        $shortest     = new LoginIdentifier('+12');
        $shortContact = $shortest->contact();
        self::assertInstanceOf(AccountMobile::class, $shortContact);
        self::assertSame('+12', $shortContact->toString());
        $longest     = new LoginIdentifier('+123456789012345');
        $longContact = $longest->contact();
        self::assertInstanceOf(AccountMobile::class, $longContact);
        self::assertSame('+123456789012345', $longContact->toString());
    }

    #[DataProvider('invalidEmails')]
    public function testEmailsRejectInvalidOrNonAsciiValues(string $value): void
    {
        $this->expectException(InvalidUserInput::class);
        new AccountEmail($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'missing domain' => ['admin'];
        yield 'non ASCII' => ['isim@örnek.com'];
        yield 'leading whitespace' => [' user@example.com'];
        yield 'trailing whitespace' => ["user@example.com\n"];
        yield 'oversized' => [str_repeat('a', 255) . '@example.com'];
    }

    #[DataProvider('invalidPasswords')]
    public function testPasswordsRejectInvalidBoundsAndEncoding(string $value): void
    {
        $this->expectException(InvalidUserInput::class);
        new Password($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPasswords(): iterable
    {
        yield 'short' => [str_repeat('a', 11)];
        yield 'long' => [str_repeat('a', 129)];
        yield 'null byte' => ["password\0abcdefgh"];
        yield 'invalid UTF8' => [str_repeat("\xff", 12)];
    }
}
