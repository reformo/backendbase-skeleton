<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RealmTokenCodec;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

use function array_keys;
use function sort;

final class JwtContractTest extends TestCase
{
    #[DataProvider('realms')]
    public function testIssuedJwtPreservesThePublicIdentityClaimContract(Realm $realm, string $actorType): void
    {
        $identity  = ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 3];
        $principal = new Principal($realm, $identity);
        $codec     = new RealmTokenCodec(IdentityFixture::settings(), new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z')));
        $encoded   = $codec->encode($principal, new DateTimeImmutable('2026-10-14T10:15:00Z'));
        if ($encoded === '') {
            self::fail('The issued JWT must not be empty.');
        }

        $parser = new Parser(new JoseEncoder());
        $token  = $parser->parse($encoded);
        self::assertInstanceOf(UnencryptedToken::class, $token);
        $claims = $token->claims();
        self::assertSame($identity['actorId'], $claims->get('sub'));
        self::assertSame($identity['accountId'], $claims->get('accountId'));
        self::assertNotSame($claims->get('accountId'), $claims->get('sub'));
        self::assertSame($identity['jti'], $claims->get('jti'));
        self::assertSame($identity['sid'], $claims->get('sid'));
        self::assertSame(3, $claims->get('authVersion'));
        self::assertSame($realm->value, $claims->get('realm'));
        self::assertSame($actorType, $claims->get('actorType'));
        $names = array_keys($claims->all());
        sort($names);
        self::assertSame(['accountId', 'actorType', 'aud', 'authVersion', 'exp', 'iat', 'iss', 'jti', 'nbf', 'realm', 'sid', 'sub'], $names);
        $headers = $token->headers();
        self::assertSame('HS256', $headers->get('alg'));
        self::assertSame('JWT', $headers->get('typ'));
    }

    /** @return iterable<string, array{Realm, string}> */
    public static function realms(): iterable
    {
        yield 'USER' => [Realm::USER, 'User'];
        yield 'ADMIN' => [Realm::ADMIN, 'SystemAdmin'];
    }
}
