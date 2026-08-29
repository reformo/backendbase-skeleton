<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\AuthorizationStateData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class AuthorizationStateDataTest extends TestCase
{
    #[Test]
    public function itRejectsNonArrayPrivileges(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AuthorizationStateData::privileges(['privileges' => 'invalid']);
    }

    #[Test]
    public function itIgnoresMalformedLegacyTokenEntries(): void
    {
        self::assertSame([], AuthorizationStateData::tokenIdentifiers(null));
        self::assertSame(['valid'], AuthorizationStateData::tokenIdentifiers([
            'tokens' => [
                'invalid',
                [],
                ['jti' => ''],
                ['jti' => 'valid'],
            ],
        ]));
    }
}
