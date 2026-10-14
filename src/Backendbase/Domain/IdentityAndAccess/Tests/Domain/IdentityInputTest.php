<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Shared\Exception\InvalidUserInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class IdentityInputTest extends TestCase
{
    public function testAsciiBusinessKeysUseStableLowercaseNormalization(): void
    {
        $login = new LoginName('INFO.User-01');
        $email = new AccountEmail('INFO.User@EXAMPLE.COM');
        self::assertSame('info.user-01', $login->toString());
        self::assertSame('info.user@example.com', $email->toString());
        $password = new Password(str_repeat('ş', 128));
        self::assertSame(str_repeat('ş', 128), $password->forHashing());
    }

    #[DataProvider('invalidLogins')]
    public function testLoginNamesRejectInputWithoutSilentSanitization(string $value): void
    {
        $this->expectException(InvalidUserInput::class);
        new LoginName($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLogins(): iterable
    {
        yield 'too short' => ['ab'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'leading separator' => ['.abc'];
        yield 'whitespace' => [' abc'];
        yield 'Turkish uppercase I' => ['İsim'];
        yield 'Turkish lowercase I' => ['ısım'];
        yield 'markup' => ['<abc>'];
        yield 'control character' => ["abc\n"];
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
