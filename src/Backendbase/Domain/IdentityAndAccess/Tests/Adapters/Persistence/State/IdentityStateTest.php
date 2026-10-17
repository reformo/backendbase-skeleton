<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\State;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\AccountState;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ActorState;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ContactState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class IdentityStateTest extends TestCase
{
    /**
     * @param class-string<AccountState|ActorState|ContactState> $class
     * @param array<string, mixed>                               $values
     */
    #[DataProviderExternal(StateExamples::class, 'values')]
    public function testStorageSerializationPreservesValuesWithoutSharingMutableArrays(string $class, array $values): void
    {
        $state         = $class::fromArray($values);
        $copy          = $state->toArray();
        $copy['extra'] = 'changed';
        self::assertSame($values, $state->toArray());
        self::assertSame($values, json_decode(json_encode($state, JSON_THROW_ON_ERROR), true, 16, JSON_THROW_ON_ERROR));
        $restored = $class::fromArray($state->toArray());
        self::assertEquals($state, $restored);
        self::assertNotSame($state, $restored);
    }

    /**
     * @param class-string<AccountState|ActorState|ContactState> $class
     * @param array<array-key, mixed>                            $values
     */
    #[DataProvider('invalidStates')]
    public function testMissingMistypedAndUnknownFieldsCannotBecomeState(string $class, array $values): void
    {
        $this->expectException(AuthenticationUnavailable::class);
        $class::fromArray($values);
    }

    /** @return iterable<string, array{class-string<AccountState|ActorState|ContactState>, array<array-key, mixed>}> */
    public static function invalidStates(): iterable
    {
        foreach (StateExamples::values() as $name => [$class, $values]) {
            yield $name . ' unknown field' => [$class, [...$values, 'futureField' => true]];
            yield $name . ' numeric field' => [$class, [...$values, 0 => true]];
            yield from self::invalidFields($class, $values);
        }
    }

    /**
     * @param class-string<AccountState|ActorState|ContactState> $class
     * @param array<string, mixed>                               $values
     *
     * @return iterable<string, array{class-string<AccountState|ActorState|ContactState>, array<string, mixed>}>
     */
    private static function invalidFields(string $class, array $values): iterable
    {
        foreach ($values as $name => $value) {
            $missing = $values;
            unset($missing[$name]);

            yield $class . ' missing ' . $name => [$class, $missing];
            yield $class . ' invalid ' . $name => [$class, [...$values, $name => []]];
        }
    }
}
